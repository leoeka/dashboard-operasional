<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\CompositionSpec;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    /** Sidecar recording what each persisted photograph was drawn for. */
    private const SUBJECTS_FILE = 'slot-subjects.json';

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
        $cardGridIndex = $this->indexForRole($plan, 'card_grid');

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
                ];
            }
        }

        if ($cardGridIndex !== null) {
            $cards = $sections[$cardGridIndex];
            $composition = CompositionSpec::resolve($cards, $design, 'card_grid');

            if ($composition['photo_slots']) {
                $items = array_values($cards['items'] ?? []);
                foreach (array_slice($items, 0, self::MAX_ITEM_PHOTOS, true) as $itemIndex => $item) {
                    $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : $item;
                    if (!is_string($title) || trim($title) === '') {
                        continue;
                    }

                    $slots['item_' . $itemIndex] = [
                        'slot' => "home.section-{$cardGridIndex}.item-{$itemIndex}",
                        'basename' => "section-{$cardGridIndex}-item-{$itemIndex}",
                        'role' => $this->itemRoleFor($composition['composition']),
                        'required' => $composition['image_required'],
                        'subject' => $title,
                        'context' => is_array($item) ? ($item['description'] ?? null) : null,
                        'section_index' => $cardGridIndex,
                        'item_index' => $itemIndex,
                    ];
                }
            }
        }

        if (!$slots) {
            return $this->empty();
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
                $filename = $entry['kind'] === 'hero'
                    ? "{$slug}-hero.{$extension}"
                    : "{$slug}-item-{$entry['item_index']}.{$extension}";

                $files[$filename] = $disk->get($path);

                if ($entry['kind'] === 'hero') {
                    $pageMap['hero'] = $filename;
                } else {
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

        foreach ($page['sections'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $itemIndex => $item) {
                $entries[] = [
                    'kind' => 'item',
                    'slot' => (string) ($item['slot'] ?? "item-{$itemIndex}"),
                    'path' => (string) ($item['path'] ?? ''),
                    'required' => (bool) ($item['required'] ?? false),
                    'item_index' => (int) $itemIndex,
                ];
            }
        }

        return $entries;
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

            $page['sections'][$slot['section_index']]['role'] = 'card_grid';
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
        return ['manifest' => ['pages' => []], 'images' => ['hero' => null, 'items' => []], 'degraded' => false, 'missing' => []];
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
        return sha1(trim((string) $slot['subject']) . '|' . trim((string) ($slot['context'] ?? '')));
    }

    /** Data URLs read back out of the files that were just written, never the pre-save bytes. */
    private function dataUrls(array $slots, array $stored): array
    {
        $disk = Storage::disk('public');
        $images = ['hero' => null, 'items' => []];

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

            $images['items'][$slots[$key]['item_index']] = $dataUrl;
        }

        return $images;
    }

    /** Which uploaded roles may fill which kind of slot, best match first. */
    private const ROLE_PREFERENCE = [
        'hero' => ['hero', 'about', 'general'],
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
     * Fires every remaining photo prompt concurrently (Http::pool()). Doing this
     * serially — up to 5 photos per candidate across 3 candidates — used to push
     * proposal generation past the queue worker's timeout.
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

        $businessType = $project->type ?: 'business';
        $styleLine = trim($visualDirection) !== '' ? " Visual treatment: {$visualDirection}." : '';

        try {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($slots, $apiKey, $businessType, $styleLine) {
                $requests = [];

                foreach ($slots as $key => $slot) {
                    $context = $slot['context'] ? " Context: {$slot['context']}." : '';
                    $prompt = $this->toSafeAscii(
                        "A single professional, photorealistic marketing photo for a {$businessType} website. Subject: \"{$slot['subject']}\".{$context}{$styleLine} "
                        . 'Natural lighting, clean uncluttered composition, no text, no watermark, no logo, no UI elements or browser chrome, square framing suitable for a website card.'
                    );

                    $requests[] = $pool->as($key)->timeout(60)->withToken($apiKey)->asJson()->post('https://api.openai.com/v1/images/generations', [
                        'model' => config('services.openai.image_model', 'gpt-image-1'),
                        'prompt' => $prompt,
                        'size' => '1024x1024',
                        'quality' => 'low',
                        'output_format' => 'jpeg',
                        'output_compression' => 70,
                    ]);
                }

                return $requests;
            });
        } catch (\Throwable $e) {
            $this->lastFailure = ProviderException::fromThrowable('openai', $e);
            Log::warning('MockupAssetService: pool generate foto mockup gagal total.', $this->lastFailure->context());

            return [];
        }

        $generated = [];

        foreach (array_keys($slots) as $key) {
            $response = $responses[$key] ?? null;

            if (!$response instanceof \Illuminate\Http\Client\Response || !$response->successful()) {
                $this->lastFailure = $response instanceof \Illuminate\Http\Client\Response
                    ? ProviderException::fromResponse('openai', $response)
                    : ProviderException::fromThrowable('openai', $response instanceof \Throwable ? $response : new \RuntimeException('Tidak ada respons.'));

                // Classification and a scrubbed detail only — a provider error
                // body can quote the request, key included.
                Log::warning('MockupAssetService: gagal generate foto mockup.', array_merge(
                    ['slot' => $key],
                    $this->lastFailure->context()
                ));
                continue;
            }

            $base64 = $response->json('data.0.b64_json');
            $bytes = $base64 ? base64_decode($base64, true) : null;

            if (is_string($bytes) && $bytes !== '') {
                $generated[$key] = ['bytes' => $bytes, 'extension' => 'jpg', 'source' => 'generated'];
            }
        }

        return $generated;
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
}
