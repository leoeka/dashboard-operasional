<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeAiCrawlerAccessJob;
use App\Jobs\AnalyzeAeoJob;
use App\Jobs\AnalyzeContentExtractabilityJob;
use App\Jobs\AnalyzeOnPageJob;
use App\Jobs\AnalyzePageSpeedJob;
use App\Jobs\AnalyzeStructuredDataJob;
use App\Jobs\AnalyzeTechnicalSeoJob;
use App\Jobs\GenerateKeywordRecommendationsJob;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SeoUrlAnalysisController extends Controller
{
    public function create()
    {
        return view('pages.seo-analysis');
    }

    public function analyze(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $url = $this->normalizeUrl($data['url']);
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || !$this->isPublicWebsiteUrl($url)) {
            return back()->withInput()->withErrors([
                'url' => 'Masukkan URL website publik yang valid dan dapat diakses.',
            ]);
        }

        $host = $this->normalizedHost($url);
        $project = Project::with('client')->latest()->get()->first(function (Project $candidate) use ($host) {
            $urls = [
                $candidate->seo_requirements['target_url'] ?? null,
                $candidate->backlink_requirements['target_url'] ?? null,
                $candidate->client?->website,
            ];

            foreach ($urls as $candidateUrl) {
                if (is_string($candidateUrl) && $candidateUrl !== '' && $this->normalizedHost($candidateUrl) === $host) {
                    return true;
                }
            }

            return false;
        });

        if (!$project) {
            $client = Client::query()->get()->first(fn ($candidate) =>
                filled($candidate->website) && $this->normalizedHost($candidate->website) === $host
            );

            if (!$client) {
                $client = Client::create([
                    'company_name' => $this->companyNameFromHost($host),
                    'contact_name' => $this->companyNameFromHost($host),
                    'website' => $url,
                    'created_by' => Auth::id(),
                ]);
            }

            $project = $client->projects()->create([
                'name' => $client->company_name . ' - Analisa SEO',
                'client_name' => $client->contact_name,
                'status' => 'request',
                'wants_seo' => true,
                'wants_backlink' => false,
                'seo_requirements' => [
                    'target_url' => $url,
                    'cms_platform' => 'lainnya',
                ],
            ]);
        } else {
            $seo = $project->seo_requirements ?? [];
            $seo['target_url'] = $url;
            $seo['cms_platform'] = $seo['cms_platform'] ?? 'lainnya';
            $project->update([
                'wants_seo' => true,
                'seo_requirements' => $seo,
            ]);
            if ($project->client && blank($project->client->website)) {
                $project->client->update(['website' => $url]);
            }
        }

        $project = $project->fresh();
        $jobs = [
            GenerateKeywordRecommendationsJob::class,
            AnalyzePageSpeedJob::class,
            AnalyzeAiCrawlerAccessJob::class,
            AnalyzeStructuredDataJob::class,
            AnalyzeOnPageJob::class,
            AnalyzeTechnicalSeoJob::class,
            AnalyzeContentExtractabilityJob::class,
            AnalyzeAeoJob::class,
        ];

        foreach ($jobs as $jobClass) {
            Cache::put($jobClass::cacheKey($project->id), [
                'status' => 'queued',
                'progress' => 0,
                'message' => 'Menunggu analisa dimulai...',
            ], now()->addMinutes(30));
            $jobClass::dispatch($project);
        }

        $project->logActivity('Analisa SEO / SXO / GEO dimulai dari URL website');

        return redirect()
            ->route('pages.project-workspace', ['project' => $project->id])
            ->with('success', 'Project analisa sudah disiapkan dan pemeriksaan berjalan.');
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    private function normalizedHost(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function isPublicWebsiteUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || isset($parts['user']) || isset($parts['pass']) || in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return str_contains($host, '.') && !str_ends_with($host, '.local');
    }

    private function companyNameFromHost(string $host): string
    {
        $label = explode('.', $host)[0] ?? $host;
        return Str::of($label)->replace(['-', '_'], ' ')->title()->toString();
    }
}
