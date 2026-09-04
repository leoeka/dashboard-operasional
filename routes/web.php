<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WebsiteBuilderController;
use App\Http\Controllers\SeoBacklinkController;
use App\Http\Controllers\MockupController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\requestOrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Web Routes - SiteFlow
|--------------------------------------------------------------------------
| Mengikuti struktur sidebar:
| Dashboard, Request Project, Proposal AI, Mockup AI, Website Generator,
| Projects, AI Workspace, QA, Reports, Settings
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');


    // 2. CRM
    // 1. Route Statis & Form (Taruh Paling Atas)
    Route::get('/clients/view', [RequestOrderController::class, 'clients'])->name('pages.crm');
    Route::get('/clients/create', [RequestOrderController::class, 'create'])->name('pages.request');
    Route::post('/clients', [RequestOrderController::class, 'store'])->name('clients.store');

    // 2. Route Dynamic Parameter (Taruh Paling Bawah)
    Route::get('/clients/{client}', [RequestOrderController::class, 'clientView'])->name('pages.crm-view');


    // 3. Project
    Route::get('/projects', [ProjectController::class, 'index'])->name('pages.projects');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('pages.projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('pages.projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('pages.projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('pages.projects.destroy');
    Route::post('/projects/{project}/files', [ProjectController::class, 'storeFile'])->name('pages.projects.files.store');
    // NOTE: destroyFile has no controller method yet (already missing
    // before this route split — pre-existing, not caused by it).
    Route::delete('/projects/{project}/files/{file}', [ProjectController::class, 'destroyFile'])->name('pages.projects.files.destroy');

    Route::get(
        '/projects/{project}/proposal/preview',
        [WebsiteBuilderController::class, 'previewProposal']
    )->name('pages.projects.proposal.preview');

    Route::get(
        '/projects/{project}/proposal/download',
        [WebsiteBuilderController::class, 'downloadProposal']
    )->name('pages.projects.proposal.download');

    // 5. AI Workspace
    Route::get('/seo-backlink', [WebsiteBuilderController::class, 'aiWorkspace'])->name('pages.seo-backlink');
    Route::get('/workshop', [WebsiteBuilderController::class, 'proposalWorkshop'])->name('pages.workshop');
    // NOTE: generateAiContent has no controller method yet (already
    // missing before this route split — pre-existing, not caused by it).
    Route::post('/seo-backlink/{project}/generate', [WebsiteBuilderController::class, 'generateAiContent'])->name('pages.seo-backlink.generate');
    Route::post('/projects/{project}/analyze-seo-backlink', [SeoBacklinkController::class, 'analyzeSeoBacklink'])
        ->name('pages.projects.seo-backlink.analyze');
    Route::get('/projects/{project}/analyze-seo-backlink/status', [SeoBacklinkController::class, 'seoBacklinkStatus'])
        ->name('pages.projects.seo-backlink.status');
    Route::post('/seo-backlink/preview/analyze', [SeoBacklinkController::class, 'analyzeSeoBacklinkPreview'])
        ->name('pages.seo-backlink.preview.analyze');
    Route::get('/seo-backlink/preview/status', [SeoBacklinkController::class, 'seoBacklinkPreviewStatus'])
        ->name('pages.seo-backlink.preview.status');
    Route::post('/projects/{project}/pagespeed/analyze', [SeoBacklinkController::class, 'analyzePageSpeed'])
        ->name('pages.projects.pagespeed.analyze');
    Route::get('/projects/{project}/pagespeed/status', [SeoBacklinkController::class, 'pageSpeedStatus'])
        ->name('pages.projects.pagespeed.status');
    Route::post('/projects/{project}/search-console/analyze', [SeoBacklinkController::class, 'analyzeSearchConsole'])
        ->name('pages.projects.search-console.analyze');
    Route::post('/projects/{project}/ga4/analyze', [SeoBacklinkController::class, 'analyzeGoogleAnalytics'])
        ->name('pages.projects.ga4.analyze');
    Route::get('/projects/{project}/seo-backlink/report/download', [SeoBacklinkController::class, 'downloadSeoBacklinkReport'])
        ->name('pages.projects.seo-backlink.report.download');
    Route::post('/projects/{project}/competitor-pagespeed/analyze', [SeoBacklinkController::class, 'analyzeCompetitorPageSpeed'])
        ->name('pages.projects.competitor-pagespeed.analyze');
    Route::get('/projects/{project}/competitor-pagespeed/status', [SeoBacklinkController::class, 'competitorPageSpeedStatus'])
        ->name('pages.projects.competitor-pagespeed.status');
    Route::post('/projects/{project}/manual-screenshot', [SeoBacklinkController::class, 'uploadManualScreenshot'])
        ->name('pages.projects.manual-screenshot.store');
    Route::delete('/projects/{project}/manual-screenshot', [SeoBacklinkController::class, 'deleteManualScreenshot'])
        ->name('pages.projects.manual-screenshot.destroy');
    Route::get('/projects/{project}/seo-proposal/download', [SeoBacklinkController::class, 'downloadSeoProposal'])
        ->name('pages.projects.seo-proposal.download');
    Route::post('/projects/{project}/competitors/select', [SeoBacklinkController::class, 'selectCompetitors'])
        ->name('pages.projects.competitors.select');
    Route::post('/projects/{project}/keywords/select', [SeoBacklinkController::class, 'selectKeywords'])
        ->name('pages.projects.keywords.select');

    // 6. Mockup (browsing gallery only — not wired to the active proposal
    // pipeline, kept because it may be reused later)
    Route::get('/mockup', [MockupController::class, 'index'])->name('pages.mockup');

    // 7. Website
    Route::get('/website', function () {
        return view('pages.website');
    })->name('pages.website');

    // 8. Finance
    Route::get('/finance', [InvoiceController::class, 'index'])->name('pages.finance');
    Route::post('/finance', [InvoiceController::class, 'store'])->name('pages.finance.store');
    Route::patch('/finance/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('pages.finance.paid');
    Route::post('/finance/{invoice}/remind', [InvoiceController::class, 'sendReminderNow'])->name('pages.finance.remind');
    Route::delete('/finance/{invoice}', [InvoiceController::class, 'destroy'])->name('pages.finance.destroy');

    // 9. Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('pages.reports');
    Route::get('/reports/download', [ReportController::class, 'downloadPdf'])->name('pages.reports.download');
    Route::get('/reports/download-excel', [ReportController::class, 'downloadExcel'])->name('pages.reports.download-excel');

    // 10. Settings
    Route::get('/about', [AboutController::class, 'index'])->name('pages.about');

    // 10. Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');

    // 10. Proposal

    Route::post('/projects/{project}/proposal/generate', [WebsiteBuilderController::class, 'generateProposal'])->name('pages.projects.proposal.generate');
    Route::get('/projects/{project}/proposal/status', [WebsiteBuilderController::class, 'proposalStatus'])
        ->name('pages.projects.proposal.status');
    Route::post('/projects/{project}/proposal/approve', [WebsiteBuilderController::class, 'approveProposal'])
        ->name('pages.projects.proposal.approve');
    Route::post('/projects/{project}/proposal/mockup/select', [WebsiteBuilderController::class, 'selectMockup'])
        ->name('pages.projects.proposal.mockup.select');

    // WordPress bundle workflow
    Route::get('/projects/{project}/bundle', [\App\Http\Controllers\BundleController::class, 'index'])->name('pages.projects.bundle');
    Route::post('/projects/{project}/bundle/build', [\App\Http\Controllers\BundleController::class, 'build'])->name('pages.projects.bundle.build');
    Route::get('/projects/{project}/bundle/download', [\App\Http\Controllers\BundleController::class, 'download'])->name('pages.projects.bundle.download');

    Route::resource('service-packages', \App\Http\Controllers\ServicePackageController::class)->except(['show']);

});

require __DIR__ . '/auth.php';
