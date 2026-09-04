<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\GoogleAnalyticsService;
use App\Services\SearchConsoleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SEO/backlink tooling: keyword recommendations, PageSpeed, Search
 * Console, GA4, competitor comparison, manual screenshots, and the
 * SEO-only PDF reports. Split out of ProjectController (see
 * WebsiteBuilderController for the AI proposal/mockup/build pipeline,
 * which used to live in the same class).
 */
class SeoBacklinkController extends Controller
{
    public function analyzeSeoBacklink(Project $project)
    {
        Cache::put(
            \App\Jobs\GenerateKeywordRecommendationsJob::cacheKey($project->id),
            ['status' => 'queued', 'progress' => 0, 'message' => 'Waiting to be processed...'],
            now()->addMinutes(10)
        );

        \App\Jobs\GenerateKeywordRecommendationsJob::dispatch($project);

        return response()->json(['queued' => true]);
    }

    public function seoBacklinkStatus(Project $project)
    {
        return response()->json(
            Cache::get(\App\Jobs\GenerateKeywordRecommendationsJob::cacheKey($project->id), [
                'status' => 'idle',
                'progress' => 0,
                'message' => '',
            ])
        );
    }

    /**
     * Mode PREVIEW: jalankan analisis SEO/Backlink TANPA project di
     * database — dipakai tombol "Analisis Sekarang" di form, sebelum
     * client/project benar-benar dibuat.
     */
    public function analyzeSeoBacklinkPreview(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
        ]);

        $token = (string) Str::uuid();

        Cache::put(
            $this->previewCacheKey($token),
            ['status' => 'queued', 'progress' => 0, 'message' => 'Waiting to be processed...'],
            now()->addMinutes(15)
        );

        \App\Jobs\RunKeywordPreviewAnalysisJob::dispatch(
            $token,
            $data['url'],
            $data['location'] ?? null,
            $data['business_name'] ?? null,
            $data['business_type'] ?? null,
        );

        return response()->json(['token' => $token]);
    }

    public function seoBacklinkPreviewStatus(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['status' => 'idle', 'progress' => 0, 'message' => '']);
        }

        return response()->json(
            Cache::get($this->previewCacheKey($token), [
                'status' => 'idle',
                'progress' => 0,
                'message' => '',
            ])
        );
    }

    private function previewCacheKey(string $token): string
    {
        return "keyword_preview:{$token}";
    }

    public function analyzePageSpeed(Project $project)
    {
        Cache::put(
            \App\Jobs\AnalyzePageSpeedJob::cacheKey($project->id),
            ['status' => 'queued', 'progress' => 0, 'message' => 'Waiting to be processed...'],
            now()->addMinutes(10)
        );

        \App\Jobs\AnalyzePageSpeedJob::dispatch($project);

        return response()->json(['queued' => true]);
    }

    public function pageSpeedStatus(Project $project)
    {
        return response()->json(
            Cache::get(\App\Jobs\AnalyzePageSpeedJob::cacheKey($project->id), [
                'status' => 'idle',
                'progress' => 0,
                'message' => '',
            ])
        );
    }

    public function analyzeSearchConsole(Project $project, SearchConsoleService $service)
    {
        $url = $project->seo_requirements['target_url']
            ?? $project->backlink_requirements['target_url']
            ?? null;

        if (!$url) {
            return response()->json(['success' => false, 'message' => 'Website URL is not available yet.'], 422);
        }

        $result = $service->getPerformance($url);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data — check whether this website is verified in the office Search Console account, or whether the GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN credential has not been set.',
            ], 422);
        }

        $seo = $project->seo_requirements ?? [];
        $seo['search_console'] = array_merge($result, ['analyzed_at' => now()->toDateTimeString()]);
        $project->update(['seo_requirements' => $seo]);

        $project->logActivity('Search Console report updated');

        return response()->json(['success' => true]);
    }

    public function analyzeGoogleAnalytics(Request $request, Project $project, GoogleAnalyticsService $service)
    {
        $url = $project->seo_requirements['target_url']
            ?? $project->backlink_requirements['target_url']
            ?? null;

        if (!$url) {
            return response()->json(['success' => false, 'message' => 'Website URL is not available yet.'], 422);
        }

        $accessToken = $service->getAccessToken();
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'GA4 credentials are incomplete — check GOOGLE_ANALYTICS_REFRESH_TOKEN in .env.',
            ], 422);
        }

        $seo = $project->seo_requirements ?? [];
        $propertyId = $request->input('property_id') ?: ($seo['ga4_property_id'] ?? null);

        if (!$propertyId) {
            $resolved = $service->resolveProperty($accessToken, $url);

            if ($resolved['status'] === 'ambiguous') {
                return response()->json([
                    'success' => false,
                    'needs_selection' => true,
                    'candidates' => $resolved['candidates'],
                    'message' => 'Found more than 1 matching GA4 Property.',
                ], 422);
            }

            if ($resolved['status'] === 'not_found') {
                return response()->json([
                    'success' => false,
                    'needs_manual_input' => true,
                    'message' => 'Could not automatically find a GA4 Property.',
                ], 422);
            }

            $propertyId = $resolved['property_id'];
        }

        $result = $service->getReport($accessToken, $propertyId);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data from GA4 — check the Property ID or account access to this property.',
            ], 422);
        }

        $seo['ga4_property_id'] = $propertyId;
        $seo['google_analytics'] = array_merge($result, ['analyzed_at' => now()->toDateTimeString()]);
        $project->update(['seo_requirements' => $seo]);

        $project->logActivity('Google Analytics (GA4) report updated');

        return response()->json(['success' => true]);
    }

    public function downloadSeoBacklinkReport(Project $project)
    {
        $seo = $project->seo_requirements ?? [];
        $backlink = $project->backlink_requirements ?? [];

        $discoveredCompetitors = collect(explode("\n", $seo['competitors'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter()
            ->values();

        $pdf = Pdf::loadView('pdf.seo-backlink-report', [
            'project' => $project,
            'seo' => $seo,
            'backlink' => $backlink,
            'aiRecommendations' => $seo['ai_recommendations'] ?? null,
            'aiTopics' => $seo['ai_identified_topics'] ?? null,
            'discoveredCompetitors' => $discoveredCompetitors,
            'competitorPagespeed' => $seo['competitor_pagespeed'] ?? null,
            'pagespeed' => $seo['pagespeed'] ?? null,
            'searchConsole' => $seo['search_console'] ?? null,
            'ga4' => $seo['google_analytics'] ?? null,
            'generatedAt' => now()->format('d F Y H:i'),
        ]);

        $clientSlug = Str::slug($project->client_name);
        $fileName = "Laporan-SEO-Backlink-{$clientSlug}-{$project->code}.pdf";

        return $pdf->download($fileName);
    }

    public function analyzeCompetitorPageSpeed(Project $project)
    {
        \App\Jobs\AnalyzeCompetitorPageSpeedJob::dispatch($project->id);

        Cache::put(
            \App\Jobs\AnalyzeCompetitorPageSpeedJob::cacheKey($project->id),
            ['status' => 'running', 'progress' => 0, 'message' => 'Starting...'],
            now()->addMinutes(10)
        );

        return response()->json(['success' => true]);
    }

    public function selectCompetitors(Request $request, Project $project)
    {
        $validated = $request->validate([
            'selected_urls' => ['nullable', 'array'],
            'selected_urls.*' => ['string'],
            'manual_urls' => ['nullable', 'string'],
        ]);

        $manualUrls = collect(explode("\n", $validated['manual_urls'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter()
            ->filter(fn($u) => filter_var($u, FILTER_VALIDATE_URL) !== false)
            ->values();

        $checkedUrls = collect($validated['selected_urls'] ?? []);

        $combined = $checkedUrls->merge($manualUrls)->unique()->values();

        if ($combined->count() > 3) {
            return response()->json([
                'success' => false,
                'message' => 'Total competitors (checked + manual) must be at most 3.',
            ], 422);
        }

        if ($combined->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least 1 competitor.',
            ], 422);
        }

        $seo = $project->seo_requirements ?? [];
        $seo['selected_competitors'] = $combined->all();
        $project->update(['seo_requirements' => $seo]);

        \App\Jobs\AnalyzeCompetitorPageSpeedJob::dispatch($project->id);

        Cache::put(
            \App\Jobs\AnalyzeCompetitorPageSpeedJob::cacheKey($project->id),
            ['status' => 'running', 'progress' => 0, 'message' => 'Starting...'],
            now()->addMinutes(10)
        );

        return response()->json(['success' => true]);
    }

    public function selectKeywords(Request $request, Project $project)
    {
        $validated = $request->validate([
            'selected_keywords' => ['nullable', 'array'],
            'selected_keywords.*' => ['string'],
        ]);

        $selected = collect($validated['selected_keywords'] ?? []);

        $seo = $project->seo_requirements ?? [];
        $mainKeywords = $seo['ai_recommendations']['main_keywords'] ?? [];

        if (empty($mainKeywords)) {
            return response()->json([
                'success' => false,
                'message' => 'No keyword analysis results yet for this project.',
            ], 422);
        }

        $mainKeywords = collect($mainKeywords)
            ->map(function ($kw) use ($selected) {
                $kw['selected'] = $selected->contains($kw['keyword'] ?? null);
                return $kw;
            })
            ->values()
            ->all();

        $seo['ai_recommendations']['main_keywords'] = $mainKeywords;
        $project->update(['seo_requirements' => $seo]);

        $project->logActivity('Keyword selection for proposal updated (' . $selected->count() . ' selected)');

        return response()->json(['success' => true]);
    }

    public function uploadManualScreenshot(Request $request, Project $project)
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'regex:/^(own_pagespeed|own_semrush|competitor_pagespeed:.+|competitor_semrush:.+)$/'],
            'screenshot' => ['required', 'image', 'max:5120'],
            'return_tab' => ['nullable', 'in:performa'],
        ]);

        $seo = $project->seo_requirements ?? [];
        $manualScreenshots = $seo['manual_screenshots'] ?? [];

        [$slotKey, $competitorHost] = array_pad(explode(':', $validated['target'], 2), 2, null);
        $isCompetitorSlot = in_array($slotKey, ['competitor_pagespeed', 'competitor_semrush']);

        $existingPath = $isCompetitorSlot
            ? (is_array($manualScreenshots[$slotKey] ?? null) ? ($manualScreenshots[$slotKey][$competitorHost] ?? null) : null)
            : ($manualScreenshots[$slotKey] ?? null);

        if ($existingPath && Storage::disk('public')->exists($existingPath)) {
            Storage::disk('public')->delete($existingPath);
        }

        $newPath = $request->file('screenshot')->store('manual-screenshots/' . $project->id, 'public');

        if ($isCompetitorSlot) {
            if (!isset($manualScreenshots[$slotKey]) || !is_array($manualScreenshots[$slotKey])) {
                $manualScreenshots[$slotKey] = [];   // reset kalau data lama rusak (bukan array)
            }
            $manualScreenshots[$slotKey][$competitorHost] = $newPath;
        } else {
            $manualScreenshots[$slotKey] = $newPath;
        }

        $seo['manual_screenshots'] = $manualScreenshots;
        $project->update(['seo_requirements' => $seo]);

        return $this->manualScreenshotRedirect($request, $project)
            ->with('success', 'Screenshot uploaded successfully.');
    }

    public function deleteManualScreenshot(Request $request, Project $project)
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'regex:/^(own_pagespeed|own_semrush|competitor_pagespeed:.+|competitor_semrush:.+)$/'],
            'return_tab' => ['nullable', 'in:performa'],
        ]);

        $seo = $project->seo_requirements ?? [];
        $manualScreenshots = $seo['manual_screenshots'] ?? [];

        [$slotKey, $competitorHost] = array_pad(explode(':', $validated['target'], 2), 2, null);
        $isCompetitorSlot = in_array($slotKey, ['competitor_pagespeed', 'competitor_semrush']);

        $path = $isCompetitorSlot
            ? (is_array($manualScreenshots[$slotKey] ?? null) ? ($manualScreenshots[$slotKey][$competitorHost] ?? null) : null)
            : ($manualScreenshots[$slotKey] ?? null);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        if ($isCompetitorSlot) {
            unset($manualScreenshots[$slotKey][$competitorHost]);
        } else {
            unset($manualScreenshots[$slotKey]);
        }

        $seo['manual_screenshots'] = $manualScreenshots;
        $project->update(['seo_requirements' => $seo]);

        return $this->manualScreenshotRedirect($request, $project)
            ->with('success', 'Screenshot deleted.');
    }

    /** Keep manually uploaded performance screenshots on the Performa tab. */
    private function manualScreenshotRedirect(Request $request, Project $project)
    {
        if ($request->input('return_tab') === 'performa') {
            return redirect()->to(route('pages.seo-backlink', ['project' => $project->id]) . '#performa');
        }

        return back();
    }

    public function downloadSeoProposal(Project $project)
    {
        $seo = $project->seo_requirements ?? [];
        $backlink = $project->backlink_requirements ?? [];

        $discoveredCompetitors = collect(explode("\n", $seo['competitors'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter()
            ->values();

        $pdf = Pdf::loadView('pdf.seo-proposal', [
            'project' => $project,
            'seo' => $seo,
            'backlink' => $backlink,
            'aiRecommendations' => $seo['ai_recommendations'] ?? null,
            'aiTopics' => $seo['ai_identified_topics'] ?? null,
            'discoveredCompetitors' => $discoveredCompetitors,
            'competitorPagespeed' => $seo['competitor_pagespeed'] ?? null,
            'pagespeed' => $seo['pagespeed'] ?? null,
            'generatedAt' => now()->format('d F Y H:i'),
        ]);

        $clientSlug = Str::slug($project->client_name);
        $fileName = "Proposal-SEO-{$clientSlug}-{$project->code}.pdf";

        return $pdf->download($fileName);
    }

    public function competitorPageSpeedStatus(Project $project)
    {
        return response()->json(
            Cache::get(
                \App\Jobs\AnalyzeCompetitorPageSpeedJob::cacheKey($project->id),
                ['status' => 'idle', 'progress' => 0, 'message' => '']
            )
        );
    }
}
