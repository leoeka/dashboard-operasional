<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Proposal;
use App\Services\GenerateMockupGptService;
use App\Services\AnalisisGeminiService;
use App\Exceptions\ProviderException;
use App\Services\BlueprintManifestService;
use App\Services\PipelineCheckpointService;
use App\Services\CompetitorContentFetcher;
use App\Services\CompetitorDiscoveryService;
use App\Services\ScreenshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The AI proposal/mockup/build pipeline: split out of ProjectController
 * (which had grown to cover project CRUD, this pipeline, AND SEO/backlink
 * tooling all in one class) so each concern has its own file. See also
 * SeoBacklinkController (SEO/backlink/PageSpeed/Search Console/GA4) and
 * BundleController (the Claude WordPress build step after a mockup is
 * approved here).
 */
class WebsiteBuilderController extends Controller
{
    public function generateProposal(Project $project)
    {
        $this->reportProgress($project, 'queued', 0, 'Waiting to be processed...');
        \App\Jobs\GenerateProposalJob::dispatch($project);
        return response()->json(['queued' => true]);
    }

    public function proposalStatus(Project $project, PipelineCheckpointService $checkpoints)
    {
        $progress = Cache::get($this->progressCacheKey($project->id), [
            'status' => 'idle',
            'progress' => 0,
            'message' => '',
        ]);

        // The cache entry expires after ten minutes; a failure has to outlive
        // it, or somebody coming back later sees "idle" and no reason to retry.
        $failure = $checkpoints->failure($project);

        if ($failure && $progress['status'] !== 'processing') {
            $progress = [
                'status' => 'failed',
                'progress' => 0,
                'message' => $failure->error_message,
                'error_code' => $failure->error_code,
                'failed_stage' => $failure->stage,
                'resume_stage' => $checkpoints->nextStage($project),
            ];
        }

        return response()->json($progress);
    }

    public function approveProposal(Project $project, BlueprintManifestService $manifestService): RedirectResponse
    {
        $proposal = $project->latestProposal;

        if (!$proposal) {
            return back()->with('error', 'Proposal belum dibuat.');
        }

        $proposalData = json_decode((string) $proposal->ai_reasoning, true) ?: [];
        $selectedIndex = (int) ($proposalData['selected_mockup_index'] ?? 0);
        $selectedMockup = $proposalData['mockup_candidates'][$selectedIndex] ?? ($proposalData['mockup'] ?? []);
        // Derived straight from the blueprint the client approved. This used
        // to round-trip through GPT vision — send the mockup PNG back and ask
        // it to describe the design in the picture — which could only lose or
        // invent detail, and made approval fail whenever OpenAI was down.
        $manifest = $manifestService->build($selectedMockup);

        $proposalData['mockup'] = $selectedMockup;
        $proposalData['implementation_manifest'] = $manifest;
        $proposal->update([
            'status' => 'approved',
            'ai_reasoning' => json_encode($proposalData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
        // projects.status only accepts request/in_progress/completed
        // (normalized in 2026_08_22_000001_normalize_project_statuses.php)
        // — 'mockup' is not a valid enum value.
        $project->update(['status' => 'in_progress']);

        return back()->with('success', 'Mockup disetujui. Sekarang data desain siap dikirim ke Claude untuk build WordPress.');
    }

    public function selectMockup(Project $project, Request $request): RedirectResponse|JsonResponse
    {
        $proposal = $project->latestProposal;
        $proposalData = json_decode((string) ($proposal?->ai_reasoning ?? ''), true) ?: [];
        $selectedIndex = (int) $request->validate(['mockup_index' => 'required|integer|min:0|max:2'])['mockup_index'];

        // Dipanggil lewat fetch() dari kartu pilihan mockup (lihat
        // seo-backlink.blade.php) supaya milih opsi tidak me-reload seluruh
        // halaman lagi — form biasa (tanpa JS) tetap jalan via redirect di
        // bawah, ini cuma jalur cepatnya.
        $wantsJson = $request->ajax() || $request->wantsJson();

        if (!$proposal || !isset($proposalData['mockup_candidates'][$selectedIndex])) {
            $message = 'Pilihan mockup tidak ditemukan. Generate proposal ulang.';
            return $wantsJson
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $proposalData['selected_mockup_index'] = $selectedIndex;
        $proposal->update(['ai_reasoning' => json_encode($proposalData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]);

        $message = 'Mockup pilihan ' . ($selectedIndex + 1) . ' tersimpan. Silakan lanjutkan persetujuan client.';

        return $wantsJson
            ? response()->json(['success' => true, 'message' => $message, 'selected_index' => $selectedIndex])
            : back()->with('success', $message);
    }

    /**
     * Jalankan proses pembuatan proposal & report progress ke Cache/DB.
     * Dipanggil dari GenerateProposalJob (queued, lihat generateProposal()
     * di atas).
     */
    /**
     * Runs the pipeline stage by stage, resuming where a previous attempt
     * stopped.
     *
     * Each stage's result is checkpointed the moment it succeeds, so a provider
     * failing halfway no longer throws the whole run away: the analysis and the
     * frozen blueprints survive, and a retry re-runs only the stage that failed.
     * That is also what makes a retry idempotent — nothing upstream is
     * recomputed, so there is no second proposal, no second set of assets and no
     * different blueprint than the one already frozen.
     */
    public function runProposalGeneration(
        Project $project,
        GenerateMockupGptService $aiService,
        AnalisisGeminiService $geminiService,
        CompetitorDiscoveryService $competitorDiscovery,
        CompetitorContentFetcher $contentFetcher,
        ?PipelineCheckpointService $checkpoints = null
    ): void {
        @set_time_limit(300);
        $checkpoints ??= app(PipelineCheckpointService::class);

        try {
            $this->runPipeline($project, $aiService, $geminiService, $competitorDiscovery, $contentFetcher, $checkpoints);
        } catch (ProviderException $e) {
            // Classification only — never a key, see ProviderException::sanitise().
            $this->reportProgress($project, 'failed', 0, $e->getMessage(), [
                'error_code' => $e->errorCode,
                'failed_stage' => $checkpoints->nextStage($project),
            ]);

            throw $e;
        }
    }

    private function runPipeline(
        Project $project,
        GenerateMockupGptService $aiService,
        AnalisisGeminiService $geminiService,
        CompetitorDiscoveryService $competitorDiscovery,
        CompetitorContentFetcher $contentFetcher,
        PipelineCheckpointService $checkpoints
    ): void {

        // 1. UPDATE PROGRESS: Load Data
        $this->reportProgress($project, 'processing', 10, 'Fetching project and client data...');

        $project->load(['client', 'files']);
        $client = $project->client;

        if (!$client) {
            $this->reportProgress($project, 'failed', 0, 'This project is not yet linked to client data.');
            throw new \Exception('Project is not linked to client data.');
        }

        // Competitor research is genuinely optional — it enriches the brief but
        // the pipeline works without it, so a failure here stays a warning.
        $competitorContents = $checkpoints->remember($project, 'competitor_research', function () use ($project, $geminiService, $competitorDiscovery, $contentFetcher) {
            if (empty(trim((string) $project->target_market))) {
                return [];
            }

            $this->reportProgress($project, 'processing', 20, 'Researching websites in the target market...');
            $found = [];

            try {
                $searchContext = $geminiService->extractCompetitorSearchContext($project);
                $competitorUrls = $competitorDiscovery->findCompetitors(
                    $searchContext['business_type'] ?? ($project->type ?? ''),
                    $searchContext['topics'] ?? [],
                    '',
                    $project->target_market
                );

                foreach (array_slice($competitorUrls, 0, 3) as $url) {
                    if (CompetitorContentFetcher::isSafeUrl($url) && ($content = $contentFetcher->fetch($url))) {
                        $found[] = array_merge($content, ['url' => $url]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Competitor research for proposal gagal, lanjut tanpa data kompetitor.', [
                    'project_id' => $project->id,
                    'error' => ProviderException::sanitise($e->getMessage()),
                ]);
            }

            return $found;
        });

        $analysis = $checkpoints->remember($project, 'gemini_analysis', function () use ($project, $client, $competitorContents, $geminiService) {
            $this->reportProgress($project, 'processing', 35, 'Analyzing business requirements with AI...');

            return $geminiService->analyzeProject($project, $client, $competitorContents);
        });

        // Frozen designs, checkpointed BEFORE any photo is paid for. An image
        // quota failure below therefore costs nothing here on retry, and the
        // retry produces assets for exactly these designs.
        $blueprints = $checkpoints->remember($project, 'design_blueprint', function () use ($project, $analysis, $competitorContents, $aiService) {
            $this->reportProgress($project, 'processing', 55, 'GPT is designing three website options...');

            return $aiService->generateMockupBlueprints($project, $analysis, $competitorContents);
        });

        $mockupCandidates = $checkpoints->remember($project, 'mockup_assets', function () use ($project, $blueprints, $aiService) {
            $this->reportProgress($project, 'processing', 70, 'Producing mockup photos and screenshots...');

            return $aiService->renderCandidates($project, $blueprints);
        });

        $mockup = $mockupCandidates[0];

        $home = collect($mockup['pages'] ?? [])->first(fn ($page) => strtolower($page['name'] ?? '') === 'home');
        $homeSections = $home['sections'] ?? [];
        $hero = collect($homeSections)->first(fn ($section) => strtolower($section['type'] ?? $section['name'] ?? '') === 'hero') ?? ($homeSections[0] ?? []);
        $newsletter = collect($homeSections)->first(fn ($section) => str_contains(strtolower((string) ($section['name'] ?? $section['type'] ?? '')), 'newsletter'));
        if (empty($mockup['screenshot_path'])) {
            $mockupHtml = view('pdf.mockup-screenshot', compact('project', 'mockup', 'homeSections', 'hero', 'newsletter'))->render();
            $mockup['screenshot_path'] = app(ScreenshotService::class)->captureHtml($mockupHtml, 'mockups/' . $project->code . '.png');
        }

        // Skipped entirely when the proposal already exists — no PDF is
        // rendered and no progress is reported for work that will not happen.
        if ($checkpoints->completed($project, 'proposal_document') === null) {
            $this->reportProgress($project, 'processing', 80, 'Assembling the PDF proposal document...');
        }
        $projectData = [
            'project_name' => $project->name,
            'client_name' => $project->client_name,
            'website_type' => $project->type ?? 'Company Profile',
            'project_code' => $project->code,
            'generated_at' => now()->format('d F Y H:i'),
        ];

        // Checkpointed like every other stage, so re-running a finished job
        // replays this instead of re-rendering the PDF, rewriting the proposal
        // and logging the activity a second time.
        $checkpoints->remember($project, 'proposal_document', function () use ($project, $projectData, $analysis, $mockup, $mockupCandidates) {
            try {
                $pdf = Pdf::loadView('pdf.proposal', compact('project', 'projectData', 'analysis', 'mockup', 'mockupCandidates'));
                $fileName = 'proposals/Proposal-Mockup-' . Str::slug($project->client_name) . '-' . $project->code . '.pdf';
                Storage::disk('public')->put($fileName, $pdf->output());

                Proposal::updateOrCreate(['project_id' => $project->id], [
                    'client_name' => $project->client_name,
                    'pdf_path' => $fileName,
                    'version' => 1,
                    'ai_reasoning' => json_encode(['analysis' => $analysis, 'mockup' => $mockup, 'mockup_candidates' => $mockupCandidates, 'selected_mockup_index' => 0], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    'summary' => $mockup['website_concept'] ?? null,
                ]);

                $project->logActivity('AI business analysis and website mockup blueprint generated');

                return ['pdf_path' => $fileName];
            } catch (\Throwable $e) {
                Log::error('PDF Error: ' . ProviderException::sanitise($e->getMessage()));
                $this->reportProgress($project, 'failed', 0, 'Failed to create PDF proposal: ' . ProviderException::sanitise($e->getMessage()));

                throw $e;
            }
        });

        $this->reportProgress($project, 'completed', 100, 'Proposal and website mockup blueprint created successfully.');
    }

    public function previewProposal(Project $project)
    {
        $proposal = Proposal::where('project_id', $project->id)
            ->latest()
            ->first();

        if (!$proposal) {
            return redirect()
                ->route('pages.projects.show', $project)
                ->with('error', 'Proposal has not been created yet.');
        }

        return view('projects.proposal-preview', compact(
            'project',
            'proposal'
        ));
    }

    public function downloadProposal(Project $project)
    {
        $proposal = Proposal::where('project_id', $project->id)
            ->latest()
            ->first();

        if (!$proposal || !$proposal->pdf_path) {
            return back()->with('error', 'PDF proposal has not been created yet.');
        }

        if (!Storage::disk('public')->exists($proposal->pdf_path)) {
            return back()->with('error', 'PDF file not found.');
        }

        return response()->download(
            Storage::disk('public')->path($proposal->pdf_path),
            basename($proposal->pdf_path),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function aiWorkspace(Request $request)
    {
        $projects = Project::orderBy('name')->get();

        $project = null;
        if ($request->project) {
            $project = Project::with(['latestProposal'])->find($request->project);
        }

        return view('pages.project-workspace', compact('projects', 'project'));
    }

    public function proposalWorkshop(Request $request)
    {
        // The original Workspace remains the primary UI while the proposal
        // workflow is refined. Keep old /workshop bookmarks working too.
        return redirect()->route('pages.project-workspace', $request->filled('project') ? [
            'project' => $request->integer('project'),
        ] : []);
    }

    private function progressCacheKey(int $projectId): string
    {
        return "proposal_progress:{$projectId}";
    }

    private function reportProgress(Project $project, string $status, int $progress, string $message, array $extra = []): void
    {
        Cache::put(
            $this->progressCacheKey($project->id),
            array_merge(['status' => $status, 'progress' => $progress, 'message' => $message], $extra),
            now()->addMinutes(10)
        );
    }
}
