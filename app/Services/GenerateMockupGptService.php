<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Models\Project;
use App\Support\CompositionSpec;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * All GPT/OpenAI calls: the mockup design pass (colors/typography per
 * candidate, layered onto AnalisisGeminiService's already-final content), the
 * mockup PNG rendering pipeline (HTML/CSS render + individually-generated
 * photos), and decomposing an approved mockup into a build manifest for
 * Claude. See AnalisisGeminiService for the Gemini side (business analysis, the
 * sitemap/copy that analysis produces, keyword research) — these two used
 * to be one class.
 */
class GenerateMockupGptService
{
    public function __construct(
        private ScreenshotService $screenshotService,
        private MockupAssetService $mockupAssets,
        private ElementorPageBuilderService $pageBuilder,
    ) {
    }
    /**
     * AI 2 — DESIGN ONLY. Gemini (analyzeBusinessWithGemini(), stored at
     * $analysis['sitemap']) already wrote the actual website content —
     * sitemap, headlines, copy, CTAs, language. GPT here does not write,
     * rewrite, or touch that content at all: it only picks the visual
     * language (colors, typography, style mood) for ONE design direction,
     * which is then merged with Gemini's untouched content in PHP. This is
     * why the 3 mockup candidates the client sees always show identical
     * content and differ ONLY in design — previously each candidate ran an
     * independent content-writing pass too, so options could show different
     * headlines/copy alongside different designs, conflating two separate
     * decisions the client has to make.
     */
    public function generateMockup(Project $project, array $analysis, string $variantInstruction = '', array $competitorContents = [], ?array $precomputedReference = null, ?array $designProfile = null): array
    {
        $sitemap = is_array($analysis['sitemap'] ?? null) ? $analysis['sitemap'] : null;
        if (!$sitemap || empty($sitemap['pages'])) {
            // There is no content to attach a design to. This used to quietly
            // substitute a locally-invented mockup, which meant a failed Gemini
            // stage still produced something that LOOKED like a finished
            // proposal. Stop instead: the analysis stage is what needs fixing.
            throw ProviderException::invalidResponse('gemini', 'Analisis tidak menyertakan sitemap/konten halaman.');
        }

        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            throw ProviderException::missingKey('openai');
        }

        $sitemapJson = json_encode($sitemap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $brandContextJson = json_encode([
            'business_analysis' => $analysis['business_analysis'] ?? [],
            'target_market' => $analysis['target_market'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $referenceType = $project->design_reference_type ?: 'none';
        $referenceUrl = $project->design_reference_url ?: 'not provided';
        $referenceFile = $project->design_reference_path ? basename($project->design_reference_path) : 'not provided';
        $variantSection = trim($variantInstruction) !== ''
            ? "\nVISUAL VARIANT DIRECTION for THIS design option:\n{$variantInstruction}\n"
            : '';

        // Every choice the designer can make is a closed vocabulary, listed
        // from CompositionSpec itself so the prompt cannot drift from what the
        // renderers actually support. The AI picks names; it never writes CSS.
        $quote = fn (array $values) => '"' . implode(' | ', $values) . '"';
        $heroCompositions = $quote(CompositionSpec::HERO_COMPOSITIONS);
        $sectionCompositions = $quote(CompositionSpec::SECTION_COMPOSITIONS);
        $containerTokens = $quote(CompositionSpec::CONTAINERS);
        $headingScales = $quote(CompositionSpec::HEADING_SCALES);
        $spacingTokens = $quote(CompositionSpec::SPACING);
        $radiusTokens = $quote(CompositionSpec::RADIUS);
        $shadowTokens = $quote(CompositionSpec::SHADOWS);
        $profileSection = $this->designProfileSection($designProfile);

        // Resolve once per call, unless the caller already resolved it
        // (generateMockupCandidates() does this ONCE and reuses it across
        // all 3 independent generateMockup() calls, so we don't screenshot
        // the same client/competitor URLs three times over).
        $reference = $precomputedReference ?? $this->resolveDesignReference($project, $competitorContents);
        $referenceImages = $reference['images'];
        $designSourceLine = $reference['line'];

        $prompt = <<<PROMPT
You are a senior website designer. The website's content is already final (written by a separate content stage) — your ONLY job is to choose the visual design for it: colors, typography, and style mood for one design option.

Client: {$project->client_name}
Project: {$project->name}
Website category: {$project->type}

BRAND CONTEXT (for grounding color/style choices only — do not rewrite any of this):
{$brandContextJson}

THE WEBSITE'S FINAL CONTENT (read-only — for context on tone/mood only, you are not authoring or editing any of it):
{$sitemapJson}

CLIENT DESIGN REFERENCE (use it only as inspiration; never copy branding, text, assets, or source code):
Type: {$referenceType}
Website URL: {$referenceUrl}
Uploaded file: {$referenceFile}
{$designSourceLine}{$variantSection}
COLOR GROUNDING — avoid the single most common mockup mistake: defaulting to a "safe" warm beige/tan/brown/cream palette no matter what the business is. Derive `primary_color`/`secondary_color`/`accent_color` specifically from THIS business's brand identity/positioning and target market psychographics above — a different brand identity or positioning should produce a genuinely different palette, not a variation on the same warm neutrals. A warm/earthy palette is only correct here if the brand itself is specifically about warmth, nature, or craft (e.g. artisanal food, leather goods) — for anything else (tech, healthcare, fashion, finance, sports, beauty, etc.) actively choose a palette that fits THAT brand instead (which could be cool, bold, monochrome, vibrant, dark, or anything else the brand identity actually calls for).

{$profileSection}
COMPOSITION — this is the part that decides whether the page looks designed or looks like a template. Choose compositions that suit THIS business, its audience and the reference above. Do not default to the same arrangement every time, and do not pick a photography-led composition for a business with no photography worth showing.

Return ONLY valid JSON with this exact shape — design decisions only, never content, never HTML or CSS:
{
  "style": "1 sentence describing this design option's overall mood",
  "visual_direction": "2-4 words naming the design language, e.g. 'editorial luxury' or 'structured corporate'",
  "primary_color": "#...", "secondary_color": "#...", "accent_color": "#...",
  "font_heading": "a real Google Font name", "font_body": "a real Google Font name",
  "density": {$spacingTokens},
  "container": {$containerTokens},
  "section_spacing": {$spacingTokens},
  "radius": {$radiusTokens},
  "shadow": {$shadowTokens},
  "image_treatment": "square | rounded | circle",
  "button_treatment": "solid | outline | pill | link",
  "typography_scale": "compact | standard | expressive",
  "sections": [
    {
      "role": "hero",
      "composition": {$heroCompositions},
      "text_align": "left | center | right",
      "container": {$containerTokens},
      "content_width": "a percentage between 25% and 75% for the copy column",
      "image_position": "left | right | background | above | below | none",
      "image_ratio": "1:1 | 4:3 | 3:4 | 4:5 | 5:4 | 16:9 | 3:2",
      "image_required": true,
      "heading_scale": {$headingScales},
      "spacing_top": {$spacingTokens},
      "spacing_bottom": {$spacingTokens}
    },
    {
      "role": "icon_band",
      "composition": {$sectionCompositions},
      "text_align": "left | center | right",
      "columns": 3,
      "heading_scale": {$headingScales},
      "spacing_top": {$spacingTokens},
      "spacing_bottom": {$spacingTokens}
    },
    {
      "role": "card_grid",
      "composition": {$sectionCompositions},
      "text_align": "left | center | right",
      "columns": 3,
      "card_treatment": "plain | bordered | shadowed | flush",
      "image_ratio": "1:1 | 4:3 | 3:4 | 4:5 | 5:4 | 16:9 | 3:2",
      "image_required": true,
      "heading_scale": {$headingScales},
      "spacing_top": {$spacingTokens},
      "spacing_bottom": {$spacingTokens}
    }
  ]
}

`image_required` means: this composition is broken without a photograph there. Say true only when that is genuinely so — a composition that reads fine as type alone must say false, because a candidate that declares an image it cannot supply is rejected rather than quietly shipped as text.
PROMPT;

        try {
            $messageContent = [['type' => 'text', 'text' => $prompt]];
            foreach ($referenceImages as $referenceImage) {
                $messageContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $referenceImage, 'detail' => 'high'],
                ];
            }

            $response = Http::timeout(90)->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.mockup_model', 'gpt-5-mini'),
                'messages' => [['role' => 'user', 'content' => $messageContent]],
                'response_format' => ['type' => 'json_object'],
            ]);

            if (!$response->successful()) {
                throw ProviderException::fromResponse('openai', $response);
            }

            $design = json_decode((string) $response->json('choices.0.message.content'), true);
            if (!is_array($design) || empty($design['primary_color'])) {
                throw ProviderException::invalidResponse('openai', 'Respons desainer bukan JSON desain yang valid.');
            }

            return $this->mergeDesignIntoSitemap($sitemap, $design);
        } catch (\Throwable $e) {
            // Deliberately NOT a fallback design. Quietly substituting invented
            // colours and a default layout produced a proposal that looked
            // finished while being nothing the designer actually chose — the
            // client would approve a design no stage had really made.
            throw ProviderException::fromThrowable('openai', $e);
        }
    }

    /**
     * Combines Gemini's already-final content (sitemap/copy) with GPT's
     * chosen design tokens for one candidate, into the same mockup shape
     * every downstream consumer (PDF proposal, PNG render, WordPress
     * builder) already expects — so nothing downstream needs to know that
     * content and design now come from two different AI calls.
     */
    private function mergeDesignIntoSitemap(array $sitemap, array $design): array
    {
        // The designer states per-section decisions against a structural ROLE
        // ("hero", "icon_band", "card_grid"), never an array index, so it cannot
        // attach a hero composition to the wrong section of somebody's sitemap.
        $sectionDesigns = [];
        foreach ($design['sections'] ?? [] as $entry) {
            if (is_array($entry) && is_string($entry['role'] ?? null)) {
                $role = strtolower(trim($entry['role']));
                unset($entry['role']);
                $sectionDesigns[$role] = $entry;
            }
        }
        unset($design['sections']);

        $pages = is_array($sitemap['pages'] ?? null) ? array_values($sitemap['pages']) : [];
        $homeIndex = $this->homePageIndex($pages);

        if ($sectionDesigns && $homeIndex !== null) {
            $sections = array_values($pages[$homeIndex]['sections'] ?? []);
            $plan = $this->pageBuilder->describeSections($sections, $design);

            foreach ($plan as $index => $sectionPlan) {
                $forRole = $sectionDesigns[$sectionPlan['role']] ?? null;
                if ($forRole && is_array($sections[$index] ?? null)) {
                    $sections[$index] = array_merge($sections[$index], $forRole);
                }
            }

            $pages[$homeIndex]['sections'] = $sections;
        }

        return [
            'website_concept' => $sitemap['website_concept'] ?? '',
            'design' => $design,
            'pages' => $pages,
            'global_cta' => $sitemap['global_cta'] ?? '',
            'seo' => $sitemap['seo'] ?? [],
        ];
    }

    /** Same "first page called Home, else the first page" rule the renderers use. */
    private function homePageIndex(array $pages): ?int
    {
        foreach ($pages as $index => $page) {
            if (is_array($page) && strtolower((string) ($page['name'] ?? '')) === 'home') {
                return $index;
            }
        }

        return is_array($pages[0] ?? null) ? 0 : null;
    }


    /**
     * Turns the client's reference into a structured Design Profile: the
     * CHARACTER of the reference, never its content.
     *
     * Handing a raw URL to the designer with "use this as inspiration" produced
     * nothing measurable. Naming the specific traits — how dominant photography
     * is, how much whitespace, what the cards and corners do — gives the
     * designer something it can actually act on, and gives us something we can
     * read back and check.
     *
     * Returns null when there is no reference or the extraction fails; the
     * designer then works from the business analysis alone.
     */
    public function extractDesignProfile(Project $project, array $reference): ?array
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey || empty($reference['images'])) {
            return null;
        }

        $keys = ['hero_composition', 'header_style', 'typography_character', 'whitespace_level',
            'image_dominance', 'content_density', 'card_style', 'corner_radius', 'shadow_character',
            'cta_character', 'navigation_style', 'visual_mood'];
        $shape = '"' . implode('": "...", "', $keys) . '": "..."';

        $prompt = <<<PROMPT
Describe the DESIGN CHARACTER of the attached reference screenshots for a designer who cannot see them.

Rules:
- Describe character only. Never reproduce or summarise the reference's text, brand, products or code.
- Each value is a short phrase (2-6 words), not a sentence.
- Judge only what is visible. If something is not shown, say "not visible".

Return ONLY valid JSON: { {$shape} }
PROMPT;

        try {
            $content = [['type' => 'text', 'text' => $prompt]];
            foreach ($reference['images'] as $image) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $image, 'detail' => 'low']];
            }

            $response = Http::timeout(90)->withToken($apiKey)->asJson()->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.mockup_model', 'gpt-5-mini'),
                'messages' => [['role' => 'user', 'content' => $content]],
                'response_format' => ['type' => 'json_object'],
            ]);

            $profile = json_decode((string) $response->json('choices.0.message.content'), true);
            if (!$response->successful() || !is_array($profile) || !$profile) {
                return null;
            }

            return array_intersect_key($profile, array_flip($keys)) ?: null;
        } catch (\Throwable $e) {
            Log::warning('Ekstraksi design profile dari referensi gagal; lanjut tanpa profile.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function designProfileSection(?array $profile): string
    {
        if (!$profile) {
            return '';
        }

        $lines = [];
        foreach ($profile as $key => $value) {
            if (is_scalar($value)) {
                $lines[] = '- ' . str_replace('_', ' ', (string) $key) . ': ' . $value;
            }
        }

        if (!$lines) {
            return '';
        }

        return "\nREFERENCE DESIGN PROFILE — the character extracted from the client's reference, not its content. Design in this character; never copy the reference itself:\n"
            . implode("\n", $lines) . "\n";
    }

    /**
     * Produces 3 design options for the SAME content. Gemini
     * (analyzeBusinessWithGemini(), $analysis['sitemap']) already wrote the
     * site's actual content once — sitemap, copy, CTAs. Each candidate here
     * calls generateMockup() independently, but generateMockup() no longer
     * touches content at all, it only picks design tokens for that one
     * option (see its docblock) — so all 3 candidates the client compares
     * show identical copy and differ ONLY in visual design, never in what
     * the page actually says.
     */
    public function generateMockupCandidates(Project $project, array $analysis, array $competitorContents = []): array
    {
        return $this->renderCandidates($project, $this->generateMockupBlueprints($project, $analysis, $competitorContents));
    }

    /**
     * PASS 1 — design only, and the last point at which a design may change.
     *
     * Split out so the pipeline can checkpoint it: a finished set of blueprints
     * survives an image-quota failure downstream, and the retry produces assets
     * for exactly the designs that were already frozen rather than asking the
     * designer for new ones. See PipelineCheckpointService.
     */
    public function generateMockupBlueprints(Project $project, array $analysis, array $competitorContents = []): array
    {
        // Resolve the visual reference (client's own, or real competitor
        // screenshots) ONCE — screenshotting is comparatively slow, no
        // need to repeat it per candidate — then reuse it across 3
        // INDEPENDENT generateMockup() calls. Each candidate gets its own
        // full GPT generation (not just a restyled copy of one shared
        // generation) so the client sees genuinely different content
        // takes, not just different product photos on identical copy;
        // content_benchmark grounding (see generateMockup()) is what keeps
        // each of the three from drifting into something generic or
        // inconsistent with AI 1's analysis.
        $reference = $this->resolveDesignReference($project, $competitorContents);

        // Composition/typography/photography axes only — deliberately
        // generic across any business category, and deliberately WITHOUT
        // any color-temperature words ("warm", "earthy", etc). A previous
        // version said "warm neutral surfaces" here — that single fixed
        // phrase, reused verbatim for every project's Option 1 regardless
        // of business, is why a shoe brand and a coffee shop both ended up
        // with the same brown/tan palette: we were telling GPT to pick a
        // warm palette every single time. Color now comes ONLY from the
        // brand-specific instruction below (see colorGroundingLine).
        $visualDirections = [
            'Option 1 - Editorial: elegant editorial composition, expressive serif headings, generous whitespace, premium photography-led hero.',
            'Option 2 - Modern & confident: clean conversion-focused composition, strong grid, crisp sans-serif typography, bold decisive layout choices.',
            'Option 3 - Calm & approachable: soft rounded cards, friendly approachable hierarchy, understated photography, airy layout.',
        ];

        // Composition is now the designer's decision, not a fixed rotation.
        // These legacy variants survive only as the FALLBACK arrangement for a
        // candidate whose design call failed and therefore carries no
        // composition at all — without them all three fallbacks would be
        // identical. A candidate that did get a composition ignores them.
        $fallbackVariants = ['split-right', 'overlay-bg', 'split-left'];

        // The reference's design character, extracted once and given to all
        // three designers so they share a brief without sharing a layout.
        $designProfile = $this->extractDesignProfile($project, $reference);

        $count = max(2, min(3, (int) config('services.openai.mockup_candidate_count', 3)));
        $blueprints = [];

        // PASS 1 — design only. Nothing is generated, persisted or rendered
        // here, because the distinctness pass below may still change a
        // candidate's composition, and a composition that changes after its
        // assets or screenshot exist is exactly the mismatch this pipeline
        // exists to prevent.
        for ($index = 0; $index < $count; $index++) {
            $candidate = $this->generateMockup($project, $analysis, $visualDirections[$index], $competitorContents, $reference, $designProfile);
            // Normalized HERE (not just before rendering the PNG) so every
            // consumer of the stored candidate — the PDF proposal template,
            // the approved-mockup decomposition step, the WordPress
            // builder — gets guaranteed strings too, not just the mockup
            // PNG render. See normalizeMockupForRender()'s docblock.
            $candidate = $this->normalizeMockupForRender($candidate);
            if (!$this->heroComposition($candidate)) {
                $candidate['design']['layout_variant'] = $fallbackVariants[$index % count($fallbackVariants)];
            }
            $candidate['candidate_number'] = $index + 1;
            $candidate['candidate_label'] = str_replace('Option ' . ($index + 1) . ' - ', '', $visualDirections[$index]);
            $candidate['client_logo_path'] = $project->client?->logo_path
                ? Storage::disk('public')->path($project->client->logo_path)
                : null;
            $candidate['design_reference_type'] = $project->design_reference_type;
            $candidate['design_reference_url'] = $project->design_reference_url;
            // Carried on the blueprint itself so pass 2 can style photographs
            // consistently even when it runs in a separate retry, from a
            // checkpoint, with no memory of this loop.
            $candidate['visual_direction_brief'] = $visualDirections[$index];
            $blueprints[] = $candidate;
        }

        // The last moment a design may legally change. After this line every
        // blueprint is final.
        return $this->enforceDistinctDesigns($blueprints);
    }

    /**
     * PASS 2 — FROZEN. INVARIANT: once a candidate's assets or screenshot
     * generation starts, its design blueprint is immutable. Required photos are
     * derived from the final composition, the persisted files belong to that
     * composition, and the PNG the client approves shows that same composition —
     * so approving the picture approves the blueprint that built it. Never
     * mutate a candidate's design/pages here.
     *
     * Separately retryable: re-running this after an image-quota failure repeats
     * only the asset and screenshot work, against the same frozen blueprints,
     * and writes to the same deterministic paths — so a retry can neither
     * duplicate an asset nor change a design.
     */
    public function renderCandidates(Project $project, array $blueprints): array
    {
        $candidates = [];

        foreach ($blueprints as $index => $candidate) {
            $assets = $this->mockupAssets->generateForCandidate(
                $project,
                $candidate,
                $index + 1,
                (string) ($candidate['visual_direction_brief'] ?? '')
            );
            $candidate['assets'] = $assets['manifest'];
            $candidate['degraded'] = $assets['degraded'];
            $candidate['missing_assets'] = $assets['missing'];
            // A degraded candidate is discarded by presentableCandidates() a
            // moment from now; rendering a picture of a design that is missing a
            // photo it requires would only produce a misleading screenshot.
            $candidate['screenshot_path'] = $assets['degraded']
                ? null
                : $this->generateMockupImage($project, $candidate, $index + 1, $assets['images']);
            $candidates[] = $candidate;
        }

        return $this->presentableCandidates($candidates, $project);
    }

    /**
     * A candidate's structural identity: the hero composition plus the
     * compositions of the sections that actually render, in order.
     *
     * e.g. "editorial|feature_grid|standard_cards". Two candidates with the
     * same fingerprint are the same design in different paint, which is the
     * complaint this whole phase exists to answer — comparing heroes alone
     * missed the case where three options differ only in hero and are otherwise
     * identical.
     */
    public function designFingerprint(array $candidate): string
    {
        $sections = $this->homeSections($candidate);
        $design = is_array($candidate['design'] ?? null) ? $candidate['design'] : [];
        $parts = [];

        foreach ($this->pageBuilder->describeSections($sections, $design) as $plan) {
            if ($plan['rendered'] && $plan['composition']) {
                $parts[] = $plan['composition']['composition'];
            }
        }

        return implode('|', $parts);
    }

    /**
     * Three options must differ in ARRANGEMENT, not only in palette.
     *
     * The designer is asked for three different compositions, but nothing stops
     * it returning near-identical ones — and three identical arrangements in
     * different colours is the "looks like one template" complaint this phase
     * exists to fix. Two rules are enforced, in order:
     *
     * 1. No two candidates share a hero composition. A repeat is moved to an
     *    alternative from a DIFFERENT layout family, so the difference is
     *    structural rather than cosmetic.
     * 2. No two candidates share a full design fingerprint. If the heroes now
     *    differ but everything else still matches, one section composition is
     *    swapped for another that suits the same content shape.
     *
     * This runs while the blueprint is still mutable — before any asset or
     * screenshot exists — so the design the client sees is the design that was
     * frozen. See the invariant in generateMockupCandidates().
     */
    private function enforceDistinctDesigns(array $candidates): array
    {
        $usedHeroes = [];

        foreach ($candidates as $index => $candidate) {
            $hero = $this->heroComposition($candidate);

            if (!$hero) {
                continue;
            }

            if (!in_array($hero, $usedHeroes, true)) {
                $usedHeroes[] = $hero;
                continue;
            }

            $alternative = $this->alternativeHero($usedHeroes);
            if (!$alternative) {
                continue;
            }

            $candidates[$index] = $this->withHeroComposition($candidate, $alternative);
            $usedHeroes[] = $alternative;
            Log::info('Komposisi hero kandidat duplikat; diganti sebelum asset & screenshot dibuat.', [
                'from' => $hero,
                'to' => $alternative,
            ]);
        }

        $usedFingerprints = [];

        foreach ($candidates as $index => $candidate) {
            $fingerprint = $this->designFingerprint($candidate);

            if ($fingerprint === '' || !in_array($fingerprint, $usedFingerprints, true)) {
                $usedFingerprints[] = $fingerprint;
                continue;
            }

            $varied = $this->varySectionComposition($candidate, $usedFingerprints);
            $candidates[$index] = $varied;
            $usedFingerprints[] = $this->designFingerprint($varied);
            Log::info('Fingerprint desain kandidat duplikat; satu komposisi section divariasikan.', [
                'fingerprint' => $fingerprint,
            ]);
        }

        return $candidates;
    }

    /** An unused hero composition, preferring one from a layout family nobody has used yet. */
    private function alternativeHero(array $used): ?string
    {
        $usedFamilies = array_map(fn (string $c) => CompositionSpec::heroFamily($c), $used);
        $fallback = null;

        foreach (CompositionSpec::HERO_COMPOSITIONS as $candidate) {
            if (in_array($candidate, $used, true)) {
                continue;
            }

            if (!in_array(CompositionSpec::heroFamily($candidate), $usedFamilies, true)) {
                return $candidate;
            }

            $fallback ??= $candidate;
        }

        return $fallback;
    }

    /**
     * Swaps ONE rendered section's composition for another that genuinely suits
     * the same content — judged by what the section's items actually are, not
     * merely by whether it carries photographs (see
     * CompositionSpec::compatibleSectionCompositions()).
     *
     * If no compatible alternative exists the candidate is returned untouched.
     * Two candidates sharing a section is a much smaller problem than a band of
     * features rendered as a pricing table, so distinctness is never forced.
     */
    private function varySectionComposition(array $candidate, array $usedFingerprints): array
    {
        $sections = $this->homeSections($candidate);
        $design = is_array($candidate['design'] ?? null) ? $candidate['design'] : [];
        $plan = $this->pageBuilder->describeSections($sections, $design);

        foreach ($plan as $sectionIndex => $sectionPlan) {
            if (!$sectionPlan['rendered'] || $sectionPlan['role'] === 'hero') {
                continue;
            }

            $current = $sectionPlan['composition']['composition'];

            foreach (CompositionSpec::compatibleSectionCompositions($sectionPlan['role'], $sections[$sectionIndex]) as $alternative) {
                if ($alternative === $current) {
                    continue;
                }

                $attempt = $this->withSectionComposition($candidate, $sectionIndex, $alternative);
                if (!in_array($this->designFingerprint($attempt), $usedFingerprints, true)) {
                    return $attempt;
                }
            }
        }

        return $candidate;
    }

    /**
     * Every design option the product promises must actually be complete.
     *
     * We sell three mockup options, so shipping two because the third could not
     * get its photographs is quietly delivering less than was agreed. A partial
     * set is therefore a failed stage, not a smaller success: the proposal is
     * not written, and the client is shown nothing.
     *
     * Nothing healthy is thrown away, though. The candidates that did succeed
     * keep their persisted photographs on disk, so the retry reuses them and
     * pays only for the slots still missing (see MockupAssetService's
     * reusePersisted()).
     */
    private function presentableCandidates(array $candidates, Project $project): array
    {
        $degraded = array_values(array_filter($candidates, fn (array $candidate) => !empty($candidate['degraded'])));

        if (!$degraded) {
            return $candidates;
        }

        $failures = [];
        foreach ($candidates as $index => $candidate) {
            if (!empty($candidate['degraded'])) {
                $failures[] = 'opsi ' . ($candidate['candidate_number'] ?? $index + 1)
                    . ' (' . implode(', ', $candidate['missing_assets'] ?? []) . ')';
            }
        }

        $healthy = count($candidates) - count($degraded);

        // Report the provider's actual reason, so the failure says what to fix
        // and whether retrying can help. The blueprints stay checkpointed, so a
        // retry re-runs only this stage, against the same frozen designs.
        $cause = $this->mockupAssets->lastFailure;

        Log::warning('Stage mockup_assets belum lengkap; kandidat sehat dipertahankan di storage untuk retry.', [
            'project_id' => $project->id,
            'lengkap' => $healthy,
            'dibutuhkan' => count($candidates),
        ]);

        throw new ProviderException(
            $cause?->provider ?? 'openai',
            $cause?->errorCode ?? ProviderException::INVALID_RESPONSE,
            'Baru ' . $healthy . ' dari ' . count($candidates) . ' opsi mockup yang lengkap. Belum ada foto untuk '
            . implode('; ', $failures) . '.' . ($cause ? ' ' . $cause->getMessage() : '')
            . ' Opsi yang sudah jadi tetap tersimpan — retry hanya melengkapi yang kurang.',
            $cause?->detail,
        );
    }

    /** The hero composition this candidate's blueprint actually asks for, if any. */
    private function heroComposition(array $candidate): ?string
    {
        $section = $this->heroSectionRef($candidate);

        return is_string($section['composition'] ?? null) ? $section['composition'] : null;
    }

    private function withHeroComposition(array $candidate, string $composition): array
    {
        return $this->withSectionComposition($candidate, 0, $composition);
    }

    /**
     * Replaces a section's composition as a SYSTEM decision, which means every
     * composition-derived field goes with it.
     *
     * The designer chose those fields to suit the composition it picked — a
     * photo-free hero comes with image_required false and centered text. Leaving
     * them on a replacement composition produces a blueprint that contradicts
     * itself: CompositionSpec would report a photo-led hero that needs no
     * photograph, and the asset step would skip an image the design shows.
     * Dropping them lets the new composition's own defaults apply. Content is
     * never touched — see CompositionSpec::COMPOSITION_DERIVED_KEYS.
     */
    private function withSectionComposition(array $candidate, int $sectionIndex, string $composition): array
    {
        $pages = array_values($candidate['pages'] ?? []);
        $homeIndex = $this->homePageIndex($pages);

        if ($homeIndex === null) {
            return $candidate;
        }

        $sections = array_values($pages[$homeIndex]['sections'] ?? []);
        if (!is_array($sections[$sectionIndex] ?? null)) {
            return $candidate;
        }

        $sections[$sectionIndex] = array_diff_key(
            $sections[$sectionIndex],
            array_flip(CompositionSpec::COMPOSITION_DERIVED_KEYS)
        );
        $sections[$sectionIndex]['composition'] = $composition;

        $pages[$homeIndex]['sections'] = $sections;
        $candidate['pages'] = $pages;

        return $candidate;
    }

    private function heroSectionRef(array $candidate): array
    {
        $sections = $this->homeSections($candidate);

        return is_array($sections[0] ?? null) ? $sections[0] : [];
    }

    /** @return array<int, array> the Home page's sections, sequentially indexed. */
    private function homeSections(array $candidate): array
    {
        $pages = array_values($candidate['pages'] ?? []);
        $homeIndex = $this->homePageIndex($pages);

        return $homeIndex === null ? [] : array_values($pages[$homeIndex]['sections'] ?? []);
    }

    /**
     * Renders the mockup as a real HTML/CSS page (design tokens, actual
     * copy, a guaranteed-present footer) and screenshots it — instead of
     * asking gpt-image-1 to draw an entire multi-section webpage as one
     * image. That approach was repeatedly cropping the footer mid-section
     * and inventing content/imagery that wasn't in the blueprint (an
     * inherent limitation of single-shot image generation for a complex,
     * text-heavy, precisely-laid-out composition — no amount of prompt
     * wording fixed it reliably). HTML capture cannot crop: Browsershot
     * screenshots the full scrollable page height. AI is only asked to
     * draw individual product/hero PHOTOS (see MockupAssetService), which
     * is a task it's actually reliable at.
     *
     * This method no longer generates anything: $images holds data URLs that
     * MockupAssetService already read back out of the persisted files, so the
     * render cannot show a photo that was not saved. It also means a mockup
     * still renders (text-only) when OpenAI is unavailable, instead of the
     * whole proposal failing.
     *
     * INVARIANT: once a candidate's assets or screenshot generation starts, its
     * design blueprint is immutable. The composition resolved here is the one
     * the client sees and approves, so it must already be final.
     *
     * @param array{hero: ?string, items: array<int, string>} $images
     */
    public function generateMockupImage(Project $project, array $mockup, int $candidateNumber, array $images): ?string
    {
        // GPT's JSON doesn't always match the requested schema exactly —
        // a "headline"/"description"/"cta"/"global_cta" field sometimes
        // comes back as an array instead of a string. Normalize every
        // text-bearing field to a real string ONCE, up front, so nothing
        // downstream (section picking, photo prompts, and Blade's
        // {{ }} which calls htmlspecialchars() and fatals on a non-string)
        // has to guess or re-check.
        $mockup = $this->normalizeMockupForRender($mockup);

        $pages = is_array($mockup['pages'] ?? null) ? $mockup['pages'] : [];
        $home = collect($pages)->first(fn ($page) => is_array($page) && strtolower((string) ($page['name'] ?? '')) === 'home') ?? ($pages[0] ?? []);
        $homeSections = is_array($home['sections'] ?? null) ? array_values($home['sections']) : [];
        $hero = $homeSections[0] ?? [];

        // Which section is the icon band and which is the photo/card grid comes
        // from the page builder's plan — the same decision that drives the
        // WordPress page and the implementation manifest. This class used to
        // keep its own copy of that heuristic, which is how item photos ended up
        // generated from one section's titles and shown against another's.
        $plan = $this->pageBuilder->describeSections($homeSections, is_array($mockup['design'] ?? null) ? $mockup['design'] : []);
        $picked = ['icon' => null, 'photo' => null];
        $compositions = ['hero' => null, 'icon' => null, 'photo' => null];
        foreach ($plan as $sectionIndex => $sectionPlan) {
            if ($sectionPlan['role'] === 'hero') {
                $compositions['hero'] = $sectionPlan['composition'];
            } elseif ($sectionPlan['role'] === 'icon_band') {
                $picked['icon'] = $homeSections[$sectionIndex];
                $compositions['icon'] = $sectionPlan['composition'];
            } elseif ($sectionPlan['role'] === 'card_grid') {
                $picked['photo'] = $homeSections[$sectionIndex];
                $compositions['photo'] = $sectionPlan['composition'];
            }
        }

        $html = view('pdf.mockup-render', [
            'project' => $project,
            'mockup' => $mockup,
            'design' => is_array($mockup['design'] ?? null) ? $mockup['design'] : [],
            'pages' => $pages,
            'homeSections' => $homeSections,
            'hero' => $hero,
            'iconSection' => $picked['icon'],
            'photoSection' => $picked['photo'],
            'heroComposition' => $compositions['hero'],
            'iconComposition' => $compositions['icon'],
            'photoComposition' => $compositions['photo'],
            'heroPhoto' => $images['hero'] ?? null,
            'itemPhotos' => $images['items'] ?? [],
            'logoDataUrl' => $this->clientLogoDataUrl($project),
        ])->render();

        $path = 'mockups/' . $project->code . '-gpt-option-' . $candidateNumber . '.png';
        $saved = $this->screenshotService->captureHtml($html, $path);

        if (!$saved) {
            throw new \RuntimeException('Gagal merender PNG mockup (Browsershot).');
        }

        return $saved;
    }

    /**
     * DESIGN SOURCE resolution — the client's OWN reference (uploaded image
     * or a URL we screenshot) always wins when present. Otherwise, when we
     * have real competitor URLs (from Gemini's competitor discovery),
     * screenshot a couple of them so GPT designs from real visual
     * references instead of inventing colors/layout from nothing — same
     * principle as content_benchmark grounding content, applied to the
     * visual side. Called ONCE by generateMockupCandidates() and reused
     * across all 3 independent generateMockup() calls (screenshotting is
     * comparatively slow; no need to repeat it per candidate).
     *
     * @return array{images: array<int, string>, mode: string, line: string}
     */
    private function resolveDesignReference(Project $project, array $competitorContents): array
    {
        $referenceType = $project->design_reference_type ?: 'none';
        $referenceImages = [];
        $designSourceMode = 'none';

        if ($referenceType === 'image' && $project->design_reference_path) {
            $dataUrl = $this->referenceImageDataUrl($project);
            if ($dataUrl) {
                $referenceImages[] = $dataUrl;
                $designSourceMode = 'client';
            }
        } elseif ($referenceType === 'url' && trim((string) $project->design_reference_url) !== '') {
            $dataUrl = $this->urlToImageDataUrl($project->design_reference_url, 'design-refs/' . $project->id . '-client-ref.png');
            if ($dataUrl) {
                $referenceImages[] = $dataUrl;
                $designSourceMode = 'client';
            }
        }

        if ($designSourceMode !== 'client' && !empty($competitorContents)) {
            foreach (array_slice($competitorContents, 0, 2) as $competitor) {
                $competitorUrl = $competitor['url'] ?? null;
                if (!$competitorUrl) {
                    continue;
                }
                $dataUrl = $this->urlToImageDataUrl($competitorUrl, 'design-refs/' . $project->id . '-competitor-' . md5($competitorUrl) . '.png');
                if ($dataUrl) {
                    $referenceImages[] = $dataUrl;
                }
            }
            if ($referenceImages) {
                $designSourceMode = 'competitor';
            }
        }

        $designSourceLine = match ($designSourceMode) {
            'client' => "\nDESIGN SOURCE: the client provided their own reference — attached below as an image. Treat it as the PRIMARY and DOMINANT visual direction: extract its layout hierarchy, spacing, section order, typography mood, colour treatment, card composition, navigation treatment, and CTA placement, and reinterpret those for this client. Never copy its literal text, branding, or photos.\n",
            'competitor' => "\nDESIGN SOURCE: the client did not provide their own reference, so " . count($referenceImages) . " real competitor website(s) in this exact space are attached below as images instead. Study their overall visual language — layout patterns, typography mood, colour palette conventions, card/grid composition, spacing rhythm — and BLEND that into a design that fits this client, differentiated per content_benchmark.must_exceed above. Do not copy any single one of them directly; synthesize something that would feel at home next to them while clearly being its own brand.\n",
            default => "\nDESIGN SOURCE: no client reference or competitor screenshots were available — design from AI 1's analysis and content_benchmark above: infer a palette, typography mood, and layout style that specifically fits this business, target market, and positioning, not a generic default.\n",
        };

        return ['images' => $referenceImages, 'mode' => $designSourceMode, 'line' => $designSourceLine];
    }

    private function urlToImageDataUrl(string $url, string $relativePath): ?string
    {
        try {
            $saved = $this->screenshotService->capture($url, $relativePath);
            if (!$saved) {
                return null;
            }

            $fullPath = Storage::disk('public')->path($saved);
            if (!is_file($fullPath)) {
                return null;
            }

            $mime = mime_content_type($fullPath) ?: 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($fullPath));
        } catch (\Throwable $e) {
            Log::warning('GenerateMockupGptService: gagal mengambil screenshot referensi desain.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Coerces every text-bearing field in a mockup blueprint (page names,
     * section headline/description/cta/name, item title/description,
     * global_cta, website_concept, design tokens) to a real string. GPT's
     * JSON output doesn't always match the requested schema — a field
     * documented as a string sometimes comes back as an array (e.g. a list
     * of alternatives) — and Blade's `{{ }}` calls htmlspecialchars()
     * internally, which fatals on anything but a string. Leaves the
     * overall array structure (pages/sections/items as arrays) untouched.
     */
    private function normalizeMockupForRender(array $mockup): array
    {
        $mockup['website_concept'] = $this->flattenToString($mockup['website_concept'] ?? '');
        $mockup['global_cta'] = $this->flattenToString($mockup['global_cta'] ?? '');

        if (is_array($mockup['design'] ?? null)) {
            foreach (['style', 'primary_color', 'secondary_color', 'accent_color', 'font_heading', 'font_body'] as $key) {
                if (isset($mockup['design'][$key])) {
                    $mockup['design'][$key] = $this->flattenToString($mockup['design'][$key]);
                }
            }
        }

        if (is_array($mockup['pages'] ?? null)) {
            $mockup['pages'] = array_map(function ($page) {
                if (!is_array($page)) {
                    return $page;
                }

                $page['name'] = $this->flattenToString($page['name'] ?? '');

                if (is_array($page['sections'] ?? null)) {
                    $page['sections'] = array_map(
                        fn ($section) => is_array($section) ? $this->normalizeSectionText($section) : $section,
                        $page['sections']
                    );
                }

                return $page;
            }, $mockup['pages']);
        }

        return $mockup;
    }

    private function normalizeSectionText(array $section): array
    {
        foreach (['headline', 'description', 'cta', 'name', 'type', 'layout'] as $key) {
            if (isset($section[$key])) {
                $section[$key] = $this->flattenToString($section[$key]);
            }
        }

        if (is_array($section['items'] ?? null)) {
            $section['items'] = array_map(function ($item) {
                if (!is_array($item)) {
                    return $this->flattenToString($item);
                }
                foreach (['title', 'name', 'description', 'price'] as $key) {
                    if (isset($item[$key])) {
                        $item[$key] = $this->flattenToString($item[$key]);
                    }
                }
                return $item;
            }, $section['items']);
        }

        return $section;
    }

    /** Best-effort coercion of an unexpected type (typically an array where a string was expected) into a string. */
    private function flattenToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = array_filter(array_map(
                fn ($v) => is_string($v) || is_numeric($v) ? (string) $v : null,
                $value
            ));
            return implode(', ', $parts);
        }

        return '';
    }

    /** Client's real logo as a data URI for the mockup nav, or null if none/unreadable. */
    private function clientLogoDataUrl(Project $project): ?string
    {
        $logoPath = $project->client?->logo_path;
        if (!$logoPath) {
            return null;
        }

        try {
            $fullPath = Storage::disk('public')->path($logoPath);
            if (!is_file($fullPath) || filesize($fullPath) > 5 * 1024 * 1024) {
                return null;
            }

            $mime = mime_content_type($fullPath) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true)) {
                return null;
            }

            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($fullPath));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Return a local design-reference image as a vision-compatible data URL. */
    private function referenceImageDataUrl(Project $project): ?string
    {
        if ($project->design_reference_type !== 'image' || !$project->design_reference_path) {
            return null;
        }

        try {
            $path = Storage::disk('public')->path($project->design_reference_path);
            if (!is_file($path) || filesize($path) > 5 * 1024 * 1024) {
                return null;
            }

            $mime = mime_content_type($path) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }

            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
        } catch (\Throwable $e) {
            Log::warning('Design reference image could not be attached to AI mockup request.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Screenshots a real URL (client's own reference URL, or a real
     * competitor site) and returns it as a vision-compatible data URL, so
     * generateMockup() can show GPT what a referenced site actually looks
     * like instead of just its URL as text. Best-effort: any failure
     * (unreachable site, headless browser issue, timeout) just means that
     * particular reference is skipped, never fails the whole mockup.
     */
}
