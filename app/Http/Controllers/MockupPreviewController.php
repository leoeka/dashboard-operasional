<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\MockupSite;
use App\Support\SitemapPages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Live website demo of a mockup candidate, before WordPress exists.
 *
 * Renders the candidate's own blueprint (pages / design / assets) through the
 * same site renderer the approved PNG uses, and through the same section plan
 * WordPress is built from — so the demo a client clicks through is the design
 * they approve and the site they receive.
 *
 * Both routes live inside the `auth` group like every other project route. The
 * page never includes the system prompt, provider responses, API keys or
 * absolute paths: it is built only from blueprint content, and photographs are
 * referenced by their public storage URL.
 */
class MockupPreviewController extends Controller
{
    /** The website itself — no dashboard chrome. Loaded inside the demo iframe or on its own (fullscreen). */
    public function show(Request $request, Project $project, int $candidate): Response
    {
        $mockup = $this->candidate($project, $candidate);
        $pages = SitemapPages::ordered($mockup['pages'] ?? []);
        $slug = (string) $request->query('page', 'home');

        if (!collect($pages)->contains('slug', $slug)) {
            $slug = $pages[0]['slug'] ?? 'home';
        }

        $site = MockupSite::build($mockup, [
            'brand' => $project->client?->company_name ?? $project->name,
            'logo' => $this->logoUrl($project),
            'fixed' => false,
            'webfonts' => true,
            'page' => $slug,
            'images' => MockupSite::imagesFromManifest(is_array($mockup['assets'] ?? null) ? $mockup['assets'] : []),
            'link' => fn (string $target) => route('pages.projects.mockup.preview', [$project, $candidate, 'page' => $target]),
        ]);

        return response()
            ->view('projects.mockup-live', ['site' => $site])
            // Only this app may frame the demo, and search engines must never
            // index an unapproved client design.
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Desktop / tablet / mobile wrapper around the live demo. */
    public function demo(Project $project, int $candidate): View
    {
        $proposalData = $this->proposalData($project);
        $candidates = $proposalData['mockup_candidates'] ?? [];
        $mockup = $this->candidate($project, $candidate);

        return view('projects.mockup-demo', [
            'project' => $project,
            'candidate' => $candidate,
            'candidateCount' => count($candidates),
            'label' => (string) ($mockup['candidate_label'] ?? ''),
            'pages' => SitemapPages::ordered($mockup['pages'] ?? []),
            'selected' => (int) ($proposalData['selected_mockup_index'] ?? 0) === $candidate,
            'approved' => $project->latestProposal?->status === 'approved',
        ]);
    }

    private function candidate(Project $project, int $candidate): array
    {
        $candidates = $this->proposalData($project)['mockup_candidates'] ?? [];
        $mockup = is_array($candidates) ? ($candidates[$candidate] ?? null) : null;

        abort_unless(is_array($mockup) && is_array($mockup['pages'] ?? null), 404);

        return $mockup;
    }

    private function proposalData(Project $project): array
    {
        $proposal = $project->latestProposal;
        abort_unless($proposal, 404);

        $data = json_decode((string) $proposal->ai_reasoning, true);

        return is_array($data) ? $data : [];
    }

    private function logoUrl(Project $project): ?string
    {
        $path = $project->client?->logo_path;

        return $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($path)
            : null;
    }
}
