<?php

namespace App\Http\Controllers;

use App\Exceptions\ProviderException;
use App\Jobs\GenerateProposalJob;
use App\Models\Project;
use App\Models\Proposal;
use App\Services\AnalisisGeminiService;
use App\Services\BlueprintManifestService;
use App\Services\CompetitorContentFetcher;
use App\Services\CompetitorDiscoveryService;
use App\Services\GenerateMockupGptService;
use App\Services\PipelineCheckpointService;
use App\Services\ScreenshotService;
use App\Support\MockupSite;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebsiteBuilderController extends Controller
{
    private const PROPOSAL_PROGRESS_TTL_MINUTES = 20;

    public function generateProposal(Project $project)
    {
        $progress = Cache::get($this->progressCacheKey($project->id));
        if (! in_array($progress['status'] ?? null, ['queued', 'processing'], true)) {
            $this->reportProgress($project, 'queued', 0, 'Waiting to be processed...');
        }

        GenerateProposalJob::dispatch($project);

        return response()->json(['queued' => true]);
    }

    public function proposalStatus(Project $project, PipelineCheckpointService $checkpoints)
    {
        $progress = Cache::get($this->progressCacheKey($project->id), [
            'status' => 'idle',
            'progress' => 0,
            'message' => '',
        ]);

        // Keep active progress visible for the full 15-minute job timeout.
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

    public function approveProposal(Project $project, Request $request, BlueprintManifestService $manifestService, GenerateMockupGptService $mockups): RedirectResponse
    {
        $proposal = $project->latestProposal;

        if (! $proposal) {
            return back()->with('error', 'Proposal belum dibuat.');
        }

        $proposalData = json_decode((string) $proposal->ai_reasoning, true) ?: [];

        // The button on a mockup card sends that card's index, so recording
        // the client's choice and recording the approval are one action. They
        // used to be two endpoints, and only the approval one had a UI.
        $validated = $request->validate(['mockup_index' => 'nullable|integer|min:0|max:2']);
        $selectedIndex = isset($validated['mockup_index'])
            ? (int) $validated['mockup_index']
            : ($proposalData['selected_mockup_index'] ?? null);

        $selectedMockup = MockupSite::candidateAt($proposalData, is_int($selectedIndex) ? $selectedIndex : null);

        if (! $selectedMockup) {
            return back()->with('error', 'Opsi mockup yang dipilih tidak ditemukan. Generate proposal ulang.');
        }

        // Drafts are photographed hero-only (three heroes per proposal, not
        // 27 photos). The section photographs the chosen design really wants
        // are bought here, and its PNG is re-rendered from them, so the picture
        // that gets approved is the picture that gets delivered. When they
        // cannot be bought, the draft is approved as it stands.
        $selectedMockup = $mockups->completeCandidate($project, $selectedMockup, ((int) $selectedIndex) + 1);

        $proposalData['selected_mockup_index'] = $selectedIndex;
        $proposalData['mockup'] = $selectedMockup;
        if (isset($proposalData['mockup_candidates'][$selectedIndex])) {
            $proposalData['mockup_candidates'][$selectedIndex] = $selectedMockup;
        }
        // Derived straight from the blueprint the client approved. This used
        // to round-trip through GPT vision — send the mockup PNG back and ask
        // it to describe the design in the picture — which could only lose or
        // invent detail, and made approval fail whenever OpenAI was down.
        $proposalData['implementation_manifest'] = $manifestService->build($selectedMockup);
        $proposal->update([
            'status' => 'approved',
            'ai_reasoning' => json_encode($proposalData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
        // projects.status only accepts request/in_progress/completed
        // (normalized in 2026_08_22_000001_normalize_project_statuses.php)
        // — 'mockup' is not a valid enum value.
        $project->update(['status' => 'in_progress']);

        return back()->with('success', 'Pilihan klien tercatat pada Opsi '.($selectedIndex + 1).'. WordPress siap dibuat.');
    }

    public function reviseProposal(Project $project, PipelineCheckpointService $checkpoints): RedirectResponse
    {
        $proposal = $project->latestProposal;

        if (! $proposal) {
            return back()->with('error', 'Proposal belum dibuat.');
        }

        $proposalData = json_decode((string) $proposal->ai_reasoning, true) ?: [];

        // "Belum ada yang cocok" is an answer from the client, so it is recorded
        // rather than thrown away: only the selection is cleared, which is what
        // locks the WordPress build again, and the round the client answered
        // stays in the proposal until a new one replaces it.
        $proposalData['selected_mockup_index'] = null;
        $proposal->update([
            'status' => 'rejected',
            'ai_reasoning' => json_encode($proposalData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        // And the answer has to lead somewhere. Replaying the frozen content
        // and blueprints would hand back the same three options the client
        // just rejected, so the stages that decide how the site looks and reads
        // are dropped. The audience and the competitor research stay: they come
        // from the brief, not from the client's taste.
        $checkpoints->forget($project, ['website_content', 'design_blueprint', 'mockup_assets', 'proposal_document']);

        return back()->with('success', 'Dicatat: belum ada opsi yang dipilih klien. Buat opsi baru untuk mengirim desain yang berbeda.');
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
        // @set_time_limit(300);
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

        if (! $client) {
            $this->reportProgress($project, 'failed', 0, 'This project is not yet linked to client data.');
            throw new \Exception('Project is not linked to client data.');
        }

        // 2. TARGET MARKET: the one thing Gemini still does.
        //
        // The team usually already wrote the audience into the User Story; this
        // pulls it out into one field so the SEO side has it and the content
        // writer has something to build on. It fails soft inside
        // deriveTargetMarket(), so a Gemini outage costs a weaker brief, never
        // the whole proposal.
        $market = $checkpoints->remember($project, 'target_market', function () use ($project, $client, $geminiService) {
            $this->reportProgress($project, 'processing', 15, 'Reading the target market out of the user story...');

            return $geminiService->deriveTargetMarket($project, $client);
        });

        // A project requested without the target-market field still gets it
        // recorded, so the Workspace and the SEO feature can see what was read
        // out of the story instead of re-deriving it next run.
        if (trim((string) $project->target_market) === '' && trim((string) $market['target_market']) !== '') {
            $project->update(['target_market' => $market['target_market']]);
        }

        // Competitor research is genuinely optional — it enriches the brief but
        // the pipeline works without it, so a failure here stays a warning.
        $competitorContents = $checkpoints->remember($project, 'competitor_research', function () use ($project, $market, $competitorDiscovery, $contentFetcher) {
            if (empty(trim((string) $market['target_market']))) {
                return [];
            }

            $this->reportProgress($project, 'processing', 20, 'Researching websites in the target market...');
            $found = [];

            try {
                $competitorUrls = $competitorDiscovery->findCompetitors(
                    $market['business_type'] ?: ($project->type ?? ''),
                    $market['topics'] ?? [],
                    '',
                    $market['target_market']
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

        // 3. CONTENT: one GPT call writes the analysis, the sitemap and the
        // actual copy. Everything after this stage treats the result as final —
        // the designer adds paint on top of these words and never rewrites them.
        $analysis = $checkpoints->remember($project, 'website_content', function () use ($project, $client, $market, $competitorContents, $aiService) {
            $this->reportProgress($project, 'processing', 35, 'GPT is writing the website content...');

            return $aiService->writeWebsiteContent($project, $client, $market, $competitorContents);
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

        if (empty($mockup['screenshot_path'])) {
            // Fallback picture through the same shared site renderer as every
            // candidate PNG and the live demo — never a separate hard-coded
            // template that would show a design nobody chose.
            $mockupHtml = view('pdf.mockup-render', ['site' => MockupSite::build($mockup, [
                'brand' => $project->client?->company_name ?? $project->name,
                'fixed' => true,
                'page' => 'home',
                'images' => MockupSite::imagesFromManifest(is_array($mockup['assets'] ?? null) ? $mockup['assets'] : []),
            ])])->render();
            $mockup['screenshot_path'] = app(ScreenshotService::class)->captureHtml($mockupHtml, 'mockups/'.$project->code.'.png');
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
                $fileName = 'proposals/Proposal-Mockup-'.Str::slug($project->client_name).'-'.$project->code.'.pdf';
                Storage::disk('public')->put($fileName, $pdf->output());

                Proposal::updateOrCreate(['project_id' => $project->id], [
                    'client_name' => $project->client_name,
                    'pdf_path' => $fileName,
                    'version' => 1,
                    'ai_reasoning' => json_encode(['analysis' => $analysis, 'mockup' => $mockup, 'mockup_candidates' => $mockupCandidates, 'selected_mockup_index' => null], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    'summary' => $mockup['website_concept'] ?? null,
                ]);

                $project->logActivity('AI business analysis and website mockup blueprint generated');

                return ['pdf_path' => $fileName];
            } catch (\Throwable $e) {
                Log::error('PDF Error: '.ProviderException::sanitise($e->getMessage()));
                $this->reportProgress($project, 'failed', 0, 'Failed to create PDF proposal: '.ProviderException::sanitise($e->getMessage()));

                throw ProviderException::fromThrowable('pipeline', $e);
            }
        });

        $this->reportProgress($project, 'completed', 100, 'Proposal and website mockup blueprint created successfully.');
    }

    public function previewProposal(Project $project)
    {
        $proposal = Proposal::where('project_id', $project->id)
            ->latest()
            ->first();

        if (! $proposal) {
            return redirect()
                ->route('pages.project-workspace', ['project' => $project->id])
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

        if (! $proposal || ! $proposal->pdf_path) {
            return back()->with('error', 'PDF proposal has not been created yet.');
        }

        if (! Storage::disk('public')->exists($proposal->pdf_path)) {
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
        $project = null;
        if ($request->project) {
            $project = Project::with(['latestProposal'])->find($request->project);
        }

        return view('pages.project-workspace', compact('project'));
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
            now()->addMinutes(self::PROPOSAL_PROGRESS_TTL_MINUTES)
        );
    }
}
