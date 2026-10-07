<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\CompositionSpec;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Owns the whole life of a mockup's photographs.
 *
 * Photos used to be generated as `data:image/jpeg;base64,...` strings, pasted
 * straight into the HTML that was screenshotted, and then dropped — the bytes
 * existed only inside the composite PNG. After approval the WordPress build
 * generated an entirely new set from different prompts, so the site a client
 * received showed different pictures from the mockup they approved (and none
 * at all when generation failed, which the build swallowed silently).
 *
 * The lifecycle is now: generate or pick a real client file, persist it, point
 * the blueprint at that path, render the mockup from the persisted file, and
 * at build time ship those exact bytes. Nothing is regenerated after approval.
 *
 * Which sections get photos is not decided here. That comes from
 * ElementorPageBuilderService::describeSections() — the same plan that renders
 * the WordPress page — so an item photo can no longer be generated from one
 * section's titles and then displayed against another section's items.
 */
class MockupAssetService
{
    /** Photos per candidate: one hero + at most this many card items. */
    private const MAX_ITEM_PHOTOS = 4;

    /** V2: most photographs any one section may take. */
    private const MAX_ITEM_PHOTOS_V2 = 6;

    /** V2: photographs per candidate across all sections, on top of the hero. */
    private const MAX_SECTION_PHOTOS = 9;

    /** Sidecar recording what each persisted photograph was drawn for. */
    private const SUBJECTS_FILE = 'slot-subjects.json';

    private const PROMPT_VERSION = 'v3';
    /**
     * Why the last photo request failed, if it did. Kept so the caller can
     * report "OpenAI image quota exhausted" rather than the useless "no
     * candidate was complete" — the pipeline needs to know which provider to
     * blame and whether trying again could help.
     */
    public ?ProviderException $lastFailure = null;

    public function __construct(private ElementorPageBuilderService $pageBuilder)
    {
    }

    /**
     * Generates (or picks) and persists every photo one mockup candidate needs.
     *
     * INVARIANT: once a candidate's assets or screenshot generation starts, its
     * design blueprint is immutable. $mockup is read here to decide which
     * photos the design requires; if the caller changed the composition
     * afterwards, the persisted files would belong to a design the client never
     * saw. GenerateMockupGptService finalises every blueprint before calling
     * this, and must never mutate one after.
     *
     * @return array{manifest: array, images: array{hero: ?string, items: array<int, string>}, degraded: bool, missing: array<int, string>}
     *         manifest: storage-relative asset references to freeze into the blueprint.
     *         images: data URLs read back OUT OF THE PERSISTED FILES, for the
     *         Browsershot render — so what the client sees is provably the file
     *         that was saved, not a byte stream that was thrown away.
     *         degraded: the approved composition needs a photo this candidate
     *         could not get. The candidate must not be shown to a client as a
     *         finished design — see GenerateMockupGptService.
     */
    public function generateForCandidate(Project $project, array $mockup, int $candidateNumber, string $visualDirection = ''): array
    {
        $design = is_array($mockup['design'] ?? null) ? $mockup['design'] : [];
        $home = $this->homePage($mockup);
        $sections = is_array($home['sections'] ?? null) ? array_values($home['sections']) : [];

        if (!$sections) {
            return $this->empty();
        }

        $plan = $this->pageBuilder->describeSections($sections, $design);
        $heroIndex = $this->indexForRole($plan, 'hero');

        // Which photos this design needs, and whether it can do without them,
        // is the BLUEPRINT's decision — not a consequence of which API calls
        // happened to succeed. A composition that shows a photograph declares
        // image_required, and a candidate that cannot supply it is degraded
        // rather than quietly turning into a text-only design.
        $slots = [];

        if ($heroIndex !== null) {
            $hero = $sections[$heroIndex];
            $composition = CompositionSpec::resolve($hero, $design, 'hero');

            if ($composition['image_position'] !== 'none') {
                $slots['hero'] = [
                    'slot' => 'home.hero',
                    'basename' => 'hero',
                    'role' => 'hero',
                    'required' => $composition['image_required'],
                    'subject' => (string) ($hero['headline'] ?? $hero['name'] ?? $project->name),
                    'context' => is_string($hero['description'] ?? null) ? $hero['description'] : null,
                    'image_ratio' => $composition['image_ratio'],
                    'focal_point' => $composition['focal_point'] ?? 'center',
                    'image_position' => $composition['image_position'],
                ];
            }
        }

        $slots += CompositionSpec::isFullPage($design)
            ? $this->fullPageSectionSlots($sections, $plan)
            : $this->legacySectionSlots($sections, $plan, $design);

        if (!$slots) {
            return $this->empty();
        }

        $brief = $this->businessBrief($project, $mockup);
        foreach ($slots as $key => $slot) {
            $slots[$key]['brief'] = $brief;
        }

        $directory = $this->candidateDirectory($project, $candidateNumber);
        $disk = Storage::disk('public');

        // COST IDEMPOTENCY. A retry after a partial quota failure must not pay
        // for the photographs that already succeeded. Deterministic paths stop
        // duplicate FILES; this stops duplicate paid REQUESTS, which is the part
        // that actually costs money. Only the slots still missing a usable file
        // reach fillFromClientUploads()/generate().
        $stored = $this->reusePersisted($directory, $slots);
        $outstanding = array_diff_key($slots, $stored);

        $resolved = $this->fillFromClientUploads($project, $outstanding);
        $generated = $this->generate(array_diff_key($outstanding, $resolved), $project, $visualDirection);

        $missing = [];

        foreach ($slots as $key => $slot) {
            if (isset($stored[$key])) {
                continue; // already on disk from an earlier attempt
            }

            $asset = $resolved[$key] ?? $generated[$key] ?? null;
            $path = null;

            if ($asset) {
                $candidatePath = $directory . '/' . $slot['basename'] . '.' . $asset['extension'];
                if ($disk->put($candidatePath, $asset['bytes'])) {
                    $path = $candidatePath;
                    $this->rememberSlotSubject($directory, $slot);
                } else {
                    Log::warning('Gagal menyimpan asset mockup.', ['project_id' => $project->id, 'path' => $candidatePath]);
                }
            }

            if ($path === null) {
                if ($slot['required']) {
                    $missing[] = $slot['slot'];
                }
                continue;
            }

            $stored[$key] = ['path' => $path, 'source' => $asset['source']];
        }

        return [
            'manifest' => $this->manifest($slots, $stored, $missing),
            'images' => $this->dataUrls($slots, $stored),
            'degraded' => $missing !== [],
            'missing' => $missing,
        ];
    }

    /**
     * Pre-V2 blueprints: photographs only for the one card grid the approved PNG
     * showed, at most MAX_ITEM_PHOTOS of them. Unchanged, so an old candidate
     * retried from a checkpoint asks for exactly the photos it always did.
     */
    private function legacySectionSlots(array $sections, array $plan, array $design): array
    {
        $cardGridIndex = $this->indexForRole($plan, 'card_grid');
        if ($cardGridIndex === null) {
            return [];
        }

        $composition = CompositionSpec::resolve($sections[$cardGridIndex], $design, 'card_grid');
        if (!$composition['photo_slots']) {
            return [];
        }

        return $this->itemSlots($sections[$cardGridIndex], $cardGridIndex, 'card_grid', $composition, self::MAX_ITEM_PHOTOS);
    }

    /**
     * V2 blueprints render every section, so every section whose composition
     * shows photographs may get them — within a per-candidate budget, because
     * three candidates multiply every photo's cost.
     *
     * The budget is spent a WHOLE section at a time, in page order. A card grid
     * with photographs on two cards and blank tops on the rest looks broken; a
     * section that did not fit the budget instead renders its photo-free form
     * (monograms, colour tiles, a typographic editorial), identically in the
     * demo and in WordPress, since both only draw photographs that exist.
     */
    private function fullPageSectionSlots(array $sections, array $plan): array
    {
        $slots = [];
        $budget = self::MAX_SECTION_PHOTOS;

        foreach ($plan as $index => $sectionPlan) {
            $composition = $sectionPlan['composition'];
            if (!$sectionPlan['rendered'] || $sectionPlan['role'] === 'hero' || !$composition || !$composition['photo_slots']) {
                continue;
            }

            $section = $sections[$index];

            // An editorial split carries one photograph for the whole section.
            if ($sectionPlan['renderer'] === 'editorial') {
                $wanted = 1;
                $sectionSlots = $budget >= $wanted ? [
                    'section_' . $index . '_item_0' => [
                        'slot' => "home.section-{$index}.item-0",
                        'basename' => "section-{$index}-item-0",
                        'role' => 'editorial',
                        'required' => $composition['image_required'],
                        'subject' => (string) ($section['headline'] ?? $section['name'] ?? ''),
                        'context' => is_string($section['description'] ?? null) ? $section['description'] : null,
                        'section_index' => $index,
                        'section_role' => $sectionPlan['role'],
                        'item_index' => 0,
                        'image_ratio' => $composition['image_ratio'] ?? '4:5',
                    ],
                ] : [];
            } else {
                $sectionSlots = $this->itemSlots($section, $index, $sectionPlan['role'], $composition, min(self::MAX_ITEM_PHOTOS_V2, $sectionPlan['item_limit']));
                $wanted = count($sectionSlots);
            }

            if ($wanted === 0 || ($wanted > $budget && !$composition['image_required'])) {
                continue;
            }

            $slots += $sectionSlots;
            $budget -= $wanted;
        }

        return $slots;
    }

    private function itemSlots(array $section, int $sectionIndex, string $sectionRole, array $composition, int $limit): array
    {
        $slots = [];
        $items = array_values(is_array($section['items'] ?? null) ? $section['items'] : []);

        foreach (array_slice($items, 0, $limit, true) as $itemIndex => $item) {
            $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : $item;
            if (!is_string($title) || trim($title) === '') {
                continue;
            }

            // Keys are internal; a retry recognises paid-for photographs by
            // basename + subject (reusePersisted()), which are unchanged.
            $slots["section_{$sectionIndex}_item_{$itemIndex}"] = [
                'slot' => "home.section-{$sectionIndex}.item-{$itemIndex}",
                'basename' => "section-{$sectionIndex}-item-{$itemIndex}",
                'role' => $this->itemRoleFor($composition['composition']),
                'required' => $composition['image_required'],
                'subject' => $title,
                'context' => is_array($item) ? ($item['description'] ?? null) : null,
                'section_index' => $sectionIndex,
                'section_role' => $sectionRole,
                'item_index' => $itemIndex,
                'image_ratio' => $composition['image_ratio'] ?? '4:3',
                'focal_point' => 'center',
            ];
        }

        return $slots;
    }

    /**
     * Reads the frozen assets of an approved mockup back off disk, in the shape
     * ElementorPageBuilderService and BundleExporterService already consume.
     *
     * @return array{map: array<string, array{hero?: string, items?: array<int, string>}>, files: array<string, string>}
     * @throws \RuntimeException if an asset the approved design actually showed is gone.
     */
    public function loadApproved(array $mockup): array
    {
        $pages = $mockup['assets']['pages'] ?? [];
        $disk = Storage::disk('public');
        $map = [];
        $files = [];
        $missing = [];

        foreach ($pages as $slug => $page) {
            $pageMap = [];
            // The first card grid keeps the flat `{slug}-item-N` names every
            // build before V2 shipped; any other photographed section gets its
            // section index in the name so two sections can never overwrite each
            // other's photographs in the theme.
            $legacySection = $this->legacyCardSection($page);

            foreach ($this->flattenSlots($page) as $entry) {
                $path = (string) ($entry['path'] ?? '');

                if ($path === '' || !$disk->exists($path)) {
                    if ($entry['required'] ?? false) {
                        $missing[] = ($entry['slot'] ?? $slug) . ' -> ' . ($path ?: '(no path recorded)');
                    }
                    continue;
                }

                // Bundle filenames stay flat and page-scoped, matching what the
                // theme's importer uploads to the Media Library.
                $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
                $isLegacyItem = $entry['kind'] === 'item' && $entry['section_index'] === $legacySection;
                $filename = match (true) {
                    $entry['kind'] === 'hero' => "{$slug}-hero.{$extension}",
                    $isLegacyItem => "{$slug}-item-{$entry['item_index']}.{$extension}",
                    default => "{$slug}-section-{$entry['section_index']}-item-{$entry['item_index']}.{$extension}",
                };

                $files[$filename] = $disk->get($path);

                if ($entry['kind'] === 'hero') {
                    $pageMap['hero'] = $filename;
                    continue;
                }

                $pageMap['sections'][$entry['section_index']][$entry['item_index']] = $filename;
                if ($isLegacyItem) {
                    $pageMap['items'][$entry['item_index']] = $filename;
                }
            }

            if ($pageMap) {
                $map[$slug] = $pageMap;
            }
        }

        if ($missing) {
            throw new \RuntimeException(
                "Approved asset missing:\n- " . implode("\n- ", $missing)
                . "\nThe client approved a design that shows these images, so the build cannot ship without them."
            );
        }

        return ['map' => $map, 'files' => $files];
    }

    /**
     * Flattens one page's manifest entry into a simple list, so the caller does
     * not walk the nested page -> section -> item shape itself.
     *
     * @return array<int, array{kind:string, slot:string, path:string, required:bool, item_index:int}>
     */
    private function flattenSlots(array $page): array
    {
        $entries = [];

        if (is_array($page['hero'] ?? null)) {
            $entries[] = [
                'kind' => 'hero',
                'slot' => (string) ($page['hero']['slot'] ?? 'hero'),
                'path' => (string) ($page['hero']['path'] ?? ''),
                'required' => (bool) ($page['hero']['required'] ?? false),
                'item_index' => 0,
            ];
        }

        foreach ($page['sections'] ?? [] as $sectionIndex => $section) {
            foreach ($section['items'] ?? [] as $itemIndex => $item) {
                $entries[] = [
                    'kind' => 'item',
                    'slot' => (string) ($item['slot'] ?? "item-{$itemIndex}"),
                    'path' => (string) ($item['path'] ?? ''),
                    'required' => (bool) ($item['required'] ?? false),
                    'section_index' => (int) $sectionIndex,
                    'item_index' => (int) $itemIndex,
                ];
            }
        }

        return $entries;
    }

    /** The section whose photographs keep the pre-V2 flat filenames: the first card grid. */
    private function legacyCardSection(array $page): ?int
    {
        foreach ($page['sections'] ?? [] as $sectionIndex => $section) {
            if (($section['role'] ?? 'card_grid') === 'card_grid') {
                return (int) $sectionIndex;
            }
        }

        return null;
    }

    /**
     * Storage-relative references only — never an absolute filesystem path,
     * which would break the moment the project moved host.
     *
     * `required` is true for exactly the photos the approved mockup actually
     * displays. A slot that never got a photo is absent entirely rather than
     * being recorded as a missing requirement, so the build only fails over an
     * image the client genuinely saw and approved.
     */
    private function manifest(array $slots, array $stored, array $missing): array
    {
        $page = [];

        foreach ($slots as $key => $slot) {
            $filled = isset($stored[$key]);

            // An unfilled OPTIONAL slot is simply dropped — that design renders
            // fine without the photo. An unfilled REQUIRED slot stays on record
            // with a null path, so the gap is visible in the blueprint instead
            // of the design silently becoming something the client never saw.
            if (!$filled && !$slot['required']) {
                continue;
            }

            $entry = [
                'slot' => $slot['slot'],
                'path' => $filled ? $stored[$key]['path'] : null,
                'required' => $slot['required'],
                'source' => $filled ? $stored[$key]['source'] : 'unavailable',
            ];

            if ($key === 'hero') {
                $page['hero'] = $entry;
                continue;
            }

            $page['sections'][$slot['section_index']]['role'] = $slot['section_role'] ?? 'card_grid';
            $page['sections'][$slot['section_index']]['items'][$slot['item_index']] = $entry;
        }

        $manifest = $page ? ['pages' => ['home' => $page]] : ['pages' => []];

        if ($missing) {
            $manifest['missing'] = $missing;
        }

        return $manifest;
    }

    private function empty(): array
    {
        return ['manifest' => ['pages' => []], 'images' => ['hero' => null, 'items' => [], 'sections' => []], 'degraded' => false, 'missing' => []];
    }

    /** Card photographs mean different things depending on what the grid shows. */
    private function itemRoleFor(string $composition): string
    {
        return match ($composition) {
            'team' => 'team',
            'gallery' => 'gallery',
            default => 'product',
        };
    }

    /**
     * Photographs an earlier attempt already produced for these exact slots.
     *
     * A file only counts as reusable when its recorded subject still matches the
     * slot's subject — see rememberSlotSubject(). Without that check, a project
     * regenerated after its copy was edited would silently keep showing pictures
     * drawn for the old headlines, since the filenames are positional.
     *
     * @return array<string, array{path:string, source:string}>
     */
    private function reusePersisted(string $directory, array $slots): array
    {
        $disk = Storage::disk('public');
        $subjects = $this->storedSubjects($directory);
        $reused = [];

        foreach ($slots as $key => $slot) {
            if (($subjects[$slot['basename']] ?? null) !== $this->subjectHash($slot)) {
                continue;
            }

            foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
                $path = $directory . '/' . $slot['basename'] . '.' . $extension;

                if ($disk->exists($path) && $disk->size($path) > 0) {
                    $reused[$key] = ['path' => $path, 'source' => 'reused'];
                    break;
                }
            }
        }

        return $reused;
    }

    /**
     * Records what each stored photograph was actually of, so a later attempt
     * can tell "the same slot, already done" from "the same position, different
     * content". Kept beside the images rather than in the blueprint because it
     * describes the files on disk, not the design.
     */
    private function rememberSlotSubject(string $directory, array $slot): void
    {
        $subjects = $this->storedSubjects($directory);
        $subjects[$slot['basename']] = $this->subjectHash($slot);

        Storage::disk('public')->put(
            $directory . '/' . self::SUBJECTS_FILE,
            json_encode($subjects, JSON_UNESCAPED_UNICODE)
        );
    }

    /** @return array<string, string> basename => subject hash */
    private function storedSubjects(string $directory): array
    {
        $disk = Storage::disk('public');
        $path = $directory . '/' . self::SUBJECTS_FILE;

        if (!$disk->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function subjectHash(array $slot): string
    {
        return sha1(
            self::PROMPT_VERSION
            . '|' . trim((string) $slot['subject'])
            . '|' . trim((string) ($slot['context'] ?? ''))
            . '|' . trim((string) ($slot['brief'] ?? ''))
        );
    }

    /**
     * Data URLs read back out of the files that were just written, never the
     * pre-save bytes. `sections` addresses every photo by section and item;
     * `items` keeps the flat card-grid map older renderers read.
     */
    private function dataUrls(array $slots, array $stored): array
    {
        $disk = Storage::disk('public');
        $images = ['hero' => null, 'items' => [], 'sections' => []];
        $cardGrid = null;

        foreach ($stored as $key => $entry) {
            if (!$disk->exists($entry['path'])) {
                continue;
            }

            $mime = $disk->mimeType($entry['path']) ?: 'image/jpeg';
            $dataUrl = 'data:' . $mime . ';base64,' . base64_encode((string) $disk->get($entry['path']));

            if ($key === 'hero') {
                $images['hero'] = $dataUrl;
                continue;
            }

            $slot = $slots[$key];
            $images['sections'][$slot['section_index']][$slot['item_index']] = $dataUrl;

            if (($slot['section_role'] ?? 'card_grid') === 'card_grid') {
                $cardGrid ??= $slot['section_index'];
                if ($cardGrid === $slot['section_index']) {
                    $images['items'][$slot['item_index']] = $dataUrl;
                }
            }
        }

        return $images;
    }

    /** Which uploaded roles may fill which kind of slot, best match first. */
    private const ROLE_PREFERENCE = [
        'hero' => ['hero', 'about', 'general'],
        'editorial' => ['about', 'hero', 'general'],
        'product' => ['product', 'service', 'gallery', 'general'],
        'team' => ['team', 'general'],
        'gallery' => ['gallery', 'product', 'general'],
    ];

    /**
     * Asset priority: a real photo the client uploaded beats an invented one.
     *
     * Uploads are matched to slots BY ROLE, not by upload order. A file the
     * client named "foto-tim.jpg" is a team photo and must not become the hero
     * just because it was uploaded first; if nothing better exists the slot
     * falls back to a `general` photo, and only then to generation. Roles come
     * from metadata that already exists (upload category, then filename) — see
     * ProjectFile::assetRole() — so nothing here inspects image content.
     *
     * The client's logo is never eligible: it is site branding, handled by
     * BundleBuilderService::collectAssets(), and must never be redrawn by an AI
     * nor dropped into a photo slot.
     *
     * @return array<string, array{bytes:string, extension:string, source:string}>
     */
    private function fillFromClientUploads(Project $project, array $slots): array
    {
        $disk = Storage::disk('public');

        /** @var array<int, array{role:string, bytes:string, extension:string}> $uploads */
        $uploads = [];

        foreach ($project->files as $file) {
            if (!$file instanceof ProjectFile || $file->category !== 'foto' || !$file->file_path) {
                continue;
            }

            $role = $file->assetRole();
            if ($role === 'logo' || !$disk->exists($file->file_path)) {
                continue;
            }

            if (!str_starts_with($disk->mimeType($file->file_path) ?: '', 'image/')) {
                continue;
            }

            $bytes = $disk->get($file->file_path);
            if (!is_string($bytes) || $bytes === '') {
                continue;
            }

            $extension = strtolower(pathinfo($file->file_path, PATHINFO_EXTENSION) ?: 'jpg');
            $uploads[] = [
                'role' => $role,
                'bytes' => $bytes,
                'extension' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg',
            ];
        }

        $filled = [];

        foreach ($slots as $key => $slot) {
            $wanted = self::ROLE_PREFERENCE[$slot['role']] ?? ['general'];
            $chosen = null;

            foreach ($wanted as $role) {
                foreach ($uploads as $index => $upload) {
                    if ($upload['role'] === $role) {
                        $chosen = $index;
                        break 2;
                    }
                }
            }

            if ($chosen === null) {
                continue;
            }

            $upload = $uploads[$chosen];
            unset($uploads[$chosen]);

            $filled[$key] = [
                'bytes' => $upload['bytes'],
                'extension' => $upload['extension'],
                'source' => 'client_upload',
            ];
        }

        return $filled;
    }

    /**
     * Sends the remaining photo prompts, as many at a time as the OpenAI
     * account allows (services.openai.images_per_minute).
     *
     * Firing every prompt at once (Http::pool()) kept proposal generation
     * inside the queue timeout, but an account limited to 5 images a minute
     * then rejected everything past the fifth with HTTP 429 — so a candidate
     * needing 9 photos could never be complete. Prompts now go out in waves
     * that fit the limit; a 429 rate limit puts the slot back for another
     * wave. When the time budget runs out the rest stay missing, and the retry
     * the client is offered fills only those (reusePersisted()).
     *
     * @return array<string, array{bytes:string, extension:string, source:string}>
     */
    private function generate(array $slots, Project $project, string $visualDirection): array
    {
        if (!$slots) {
            return [];
        }

        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            $this->lastFailure = ProviderException::missingKey('openai');

            return [];
        }

        // $businessType = $project->type ?: 'business';
        $photography = $this->photographyDirection($visualDirection);
        $pending = $slots;
        $attempts = [];
        $generated = [];

        while ($pending) {
            $this->imageRunStartedAt ??= $this->clock();

            // Checked after any wait for the rate limit, which is where the time goes.
            $capacity = $this->imageCapacity(count($pending));

            if ($this->imageBudgetSpent()) {
                Log::warning('MockupAssetService: batas waktu foto habis, sisa foto dilengkapi saat retry.', ['remaining' => array_keys($pending)]);
                break;
            }

            $wave = array_slice($pending, 0, $capacity, true);

            try {
                $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($wave, $apiKey, $photography) {
                    $requests = [];

                    foreach ($wave as $key => $slot) {
                        $requests[] = $pool
                            ->as($key)
                            ->timeout(150)
                            ->withToken($apiKey)
                            ->asJson()
                            ->post('https://api.openai.com/v1/images/generations', [
                                'model' => config('services.openai.image_model', 'gpt-image-1'),
                                'prompt' => $this->photoPrompt($slot, $photography),
                                'size' => $this->photoSize($slot['image_ratio'] ?? '4:3'),
                                'quality' => config('services.openai.image_quality', 'medium'),
                                'output_format' => 'jpeg',
                                'output_compression' => 82,
                            ]);
                    }

                    return $requests;
                });
            } catch (\Throwable $e) {
                $this->lastFailure = ProviderException::fromThrowable('openai', $e);
                Log::warning('MockupAssetService: pool generate foto mockup gagal total.', $this->lastFailure->context());

                break;
            }

            $this->recordImageRequests(count($wave));

            foreach (array_keys($wave) as $key) {
                $response = $responses[$key] ?? null;

                if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                    $base64 = $response->json('data.0.b64_json');
                    $bytes = $base64 ? base64_decode($base64, true) : null;

                    if (is_string($bytes) && $bytes !== '') {
                        if ($this->reviewGeneratedImage($bytes, $pending[$key])) {
                            unset($pending[$key]);
                            $generated[$key] = ['bytes' => $bytes, 'extension' => 'jpg', 'source' => 'generated'];
                        } else {
                            $attempts[$key] = ($attempts[$key] ?? 0) + 1;
                            Log::warning('MockupAssetService: foto ditolak pemeriksaan visual.', ['slot' => $key, 'attempt' => $attempts[$key]]);
                            if ($attempts[$key] >= 2) {
                                unset($pending[$key]);
                            }
                        }
                    } else {
                        unset($pending[$key]);
                    }
                    continue;
                }

                $this->lastFailure = $response instanceof \Illuminate\Http\Client\Response
                    ? ProviderException::fromResponse('openai', $response)
                    : ProviderException::fromThrowable('openai', $response instanceof \Throwable ? $response : new \RuntimeException('Tidak ada respons.'));

                // Classification and a scrubbed detail only — a provider error
                // body can quote the request, key included.
                Log::warning('MockupAssetService: gagal generate foto mockup.', array_merge(
                    ['slot' => $key],
                    $this->lastFailure->context()
                ));

                $attempts[$key] = ($attempts[$key] ?? 0) + 1;

                if ($this->lastFailure->errorCode === ProviderException::RATE_LIMITED && $attempts[$key] < 3) {
                    // The account's minute is used up: the slot waits out a full window.
                    $this->saturateImageWindow();
                    continue;
                }

                unset($pending[$key]);
            }
        }

        return $generated;
    }

    /** Reject obvious text, poster layouts, collages, and unusable generated photos. */
    private function reviewGeneratedImage(string $bytes, array $slot): bool
    {
        if (!config('services.openai.review_generated_images', true)) {
            return true;
        }

        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return false;
        }

        $response = Http::timeout(90)->withToken($apiKey)->asJson()->post('https://api.openai.com/v1/responses', [
            'model' => config('services.openai.image_review_model', 'gpt-4.1-mini'),
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => 'Review this generated website photo. Return JSON only: {"accepted":true|false,"reason":"short reason"}. Accept only a coherent, usable photograph of one scene matching this subject: ' . (string) ($slot['subject'] ?? 'business photo') . '. Reject visible text/lettering, watermarks, logos, poster or screenshot appearance, split panels/collages, severe distortions, and unusable blur. Do not reject ordinary signs naturally present in a real scene unless they dominate the image.'],
                    ['type' => 'input_image', 'image_url' => 'data:image/jpeg;base64,' . base64_encode($bytes)],
                ],
            ]],
            'text' => ['format' => ['type' => 'json_object']],
        ]);

        if (!$response->successful()) {
            Log::warning('MockupAssetService: review visual foto gagal.', ['slot' => $slot['slot'] ?? 'unknown', 'status' => $response->status()]);
            return false;
        }

        $text = $response->json('output_text');
        if (!is_string($text)) {
            foreach ($response->json('output', []) as $output) {
                foreach (($output['content'] ?? []) as $content) {
                    if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                        $text = $content['text'];
                        break 2;
                    }
                }
            }
        }

        $review = is_string($text) ? json_decode($text, true) : null;
        return is_array($review) && ($review['accepted'] ?? false) === true;
    }

    /** @var array<int, float> when each recent image request went out, across every candidate of this run */
    private array $imageRequestTimes = [];

    private ?float $imageRunStartedAt = null;

    /**
     * How many prompts may go out now, waiting first if the account's
     * per-minute allowance is already used. 0 in config means no limit.
     */
    private function imageCapacity(int $wanted): int
    {
        $limit = (int) config('services.openai.images_per_minute', 5);
        if ($limit <= 0) {
            return $wanted;
        }

        $window = $this->openImageWindow();

        if (count($window) >= $limit) {
            $wait = (int) ceil(60 - ($this->clock() - min($window))) + 1;
            Sleep::for(max(1, $wait))->seconds();
            $window = $this->openImageWindow();
        }

        return max(1, min($wanted, $limit - count($window)));
    }

    /** The request times still inside the last minute. */
    private function openImageWindow(): array
    {
        $now = $this->clock();

        return $this->imageRequestTimes = array_values(array_filter(
            $this->imageRequestTimes,
            fn(float $at) => $now - $at < 60
        ));
    }

    private function recordImageRequests(int $count): void
    {
        $now = $this->clock();
        array_push($this->imageRequestTimes, ...array_fill(0, $count, $now));
    }

    private function saturateImageWindow(): void
    {
        $limit = max(1, (int) config('services.openai.images_per_minute', 5));
        $this->imageRequestTimes = array_fill(0, $limit, $this->clock());
    }

    /** True once this run has spent its photo time budget — the queue job has a hard timeout. */
    private function imageBudgetSpent(): bool
    {
        $budget = (int) config('services.openai.image_time_budget', 300);

        return $budget > 0 && $this->imageRunStartedAt !== null && $this->clock() - $this->imageRunStartedAt >= $budget;
    }

    /** Carbon's clock, so Sleep::fake(syncWithCarbon: true) drives the throttle in tests. */
    private function clock(): float
    {
        return now()->getPreciseTimestamp(3) / 1000;
    }

    /**
     * The prompt for one photograph.
     *
     * Two things used to wreck the pictures, and both are avoided here:
     * - the slot's headline went in as `Subject: "Experience Bali in …"`; an
     *   image model reads quoted copy as words to print, so the photos came
     *   back as posters with garbled headlines across them;
     * - the candidate's LAYOUT brief ("expressive serif headings, a composed
     *   gallery…") went in as the visual treatment, which produced typography,
     *   collages and split screens inside the photograph.
     * So the copy is described as a scene, and only a photographic direction
     * (light, colour, lens) is passed on.
     */
    private function photoPrompt(array $slot, string $photography): string
    {
        $ratio = $slot['image_ratio'] ?? '4:3';
        $orientation = match ($ratio) {
            '16:9', '3:2', '4:3', '5:4' => 'landscape',
            '4:5', '3:4' => 'portrait',
            default => 'square',
        };

        $framing = match ($slot['role'] ?? 'product') {
            'hero' => match ($slot['image_position'] ?? 'none') {
                    'background' => 'Wide establishing shot used full-bleed behind website copy: keep the left half calm and low in detail (open sky, water, soft background), put the subject to the right third.',
                    'left' => 'Hero photograph: subject toward the left third, the rest of the frame calm.',
                    'right' => 'Hero photograph: subject toward the right third, the rest of the frame calm.',
                    default => 'Wide hero photograph with one clear subject and a calm, uncluttered background.',
                },
            'editorial' => 'Editorial photograph with one clear subject, photographed close enough to feel personal.',
            'team' => 'Natural portrait of one person at work, head and shoulders, plain softly blurred background.',
            'gallery' => 'Atmospheric photograph of the place or moment, as a professional travel or documentary photographer would frame it.',
            default => 'Photograph of exactly this one thing or place, well framed, suitable as a listing or card image.',
        };

        $focal = match ($slot['focal_point'] ?? 'center') {
            'left' => ' Main subject in the left third.',
            'right' => ' Main subject in the right third.',
            'top' => ' Main subject in the upper part of the frame.',
            'bottom' => ' Main subject in the lower part of the frame.',
            default => '',
        };

        return $this->toSafeAscii(
            'A real photograph for the website of this business: ' . ($slot['brief'] ?? '') . ' '
            . 'The business description is background only, never text or a logo to show in the image. '
            . 'Scene: ' . $this->photoScene($slot) . ' '
            . 'The scene description is something to photograph, never words to show in the image. '
            . "{$framing}{$focal} {$orientation} orientation. "
            . "Photographic style: {$photography}. "
            . 'It is one continuous photograph taken with a real camera: not a graphic design, poster, flyer, magazine page, advertisement, collage, split screen, grid of photos, frame within a frame or illustration. '
            . 'No text anywhere in the image: no letters, words, numbers, captions, titles, logos, watermarks, signs, labels, badges, UI or borders. '
            . 'People, if any, are candid and natural with correct faces and hands, and nobody is cut off at the edge of the frame.'
        );
    }

    /** The slot's copy restated as something a photographer could shoot: no quotes, no marketing punctuation, bounded length. */
    private function photoScene(array $slot): string
    {
        $title = trim(preg_replace('/["“”\'‘’!?:|]+/u', ' ', (string) ($slot['subject'] ?? '')) ?? '');
        $context = trim(preg_replace('/["“”]+/u', '', (string) ($slot['context'] ?? '')) ?? '');

        // The first two sentences carry the visual; the rest is usually sales copy.
        $sentences = preg_split('/(?<=[.!?])\s+/u', $context) ?: [];
        $context = Str::limit(implode(' ', array_slice($sentences, 0, 2)), 320, '');

        $scene = preg_replace('/\s+/u', ' ', $title) ?? '';

        return rtrim($scene . ($context !== '' ? ' - ' . $context : ''), ' .') . '.';
    }

    /**
     * Light, colour and lens for a candidate, taken from its direction brief.
     * The brief itself describes layout and type, which an image model would
     * draw INTO the photograph, so it is never sent.
     */
    private function photographyDirection(string $visualDirection): string
    {
        $brief = Str::lower($visualDirection);

        return match (true) {
            str_contains($brief, 'editorial') => 'cinematic natural light, rich true-to-life colour, gentle shadows, shallow depth of field, full-frame camera with a 35mm lens',
            str_contains($brief, 'calm') || str_contains($brief, 'approachable') => 'soft diffused daylight, airy bright tones, natural colour, 50mm lens, relaxed and honest',
            str_contains($brief, 'confident') || str_contains($brief, 'modern') => 'crisp clear daylight, clean contrast, vivid but natural colour, sharp focus, 28mm lens',
            default => 'natural light, true-to-life colour, sharp focus, 35mm lens',
        };
    }

    private function photoSize(string $ratio): string
    {
        return match ($ratio) {
            '16:9', '3:2', '4:3', '5:4' => '1536x1024',
            '4:5', '3:4' => '1024x1536',
            default => '1024x1024',
        };
    }

    private function candidateDirectory(Project $project, int $candidateNumber): string
    {
        $code = Str::slug((string) ($project->code ?: 'project-' . $project->id)) ?: 'project-' . $project->id;

        return "mockup-assets/{$code}/candidate-{$candidateNumber}";
    }

    private function homePage(array $mockup): array
    {
        $pages = is_array($mockup['pages'] ?? null) ? array_values($mockup['pages']) : [];

        foreach ($pages as $page) {
            if (is_array($page) && strtolower((string) ($page['name'] ?? '')) === 'home') {
                return $page;
            }
        }

        return is_array($pages[0] ?? null) ? $pages[0] : [];
    }

    private function indexForRole(array $plan, string $role): ?int
    {
        foreach ($plan as $index => $sectionPlan) {
            if ($sectionPlan['role'] === $role) {
                return (int) $index;
            }
        }

        return null;
    }

    private function toSafeAscii(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $ascii !== false ? $ascii : (preg_replace('/[^\x00-\x7F]/', '', $value) ?? '');
    }

    private function businessBrief(Project $project, array $mockup): string
    {
        $concept = is_string($mockup['website_concept'] ?? null) ? $mockup['website_concept'] : '';
        if (trim($concept) === '') {
            $concept = (string) $project->description;
        }

        $seo = is_array($project->seo_requirements) ? $project->seo_requirements : [];
        $location = trim((string) ($seo['location'] ?? $project->client?->address ?? ''));

        $clean = fn(string $t): string => trim(preg_replace(
            '/\s+/u',
            ' ',
            preg_replace('/["“”\'‘’!?:|]+/u', ' ', $t) ?? ''
        ) ?? '');

        $brief = Str::limit($clean($concept), 260, '');

        if ($location !== '') {
            $brief = rtrim($brief, ' .') . '. Location: ' . Str::limit($clean($location), 80, '') . '.';
        }

        return trim($brief) !== '' ? trim($brief) : 'a small local business';
    }
}
