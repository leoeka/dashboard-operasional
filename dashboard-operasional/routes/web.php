<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\requestOrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SiteCleanupController;

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
    Route::delete('/projects/{project}/files/{file}', [ProjectController::class, 'destroyFile'])->name('pages.projects.files.destroy');
    Route::put('/projects/{project}/mockup', [ProjectController::class, 'addmockupTemplate'])
        ->name('pages.projects.mockup.add');


    Route::get(
        '/projects/{project}/proposal/preview',
        [ProjectController::class, 'previewProposal']
    )->name('pages.projects.proposal.preview');

    Route::get(
        '/projects/{project}/proposal/download',
        [ProjectController::class, 'downloadProposal']
    )->name('pages.projects.proposal.download');

    Route::delete('/projects/{project}/mockup/zipwp-site', [SiteCleanupController::class, 'destroy'])
        ->name('pages.projects.mockup.zipwp-delete');



    // 5. AI Workspace
    Route::get('/seo-backlink', [ProjectController::class, 'aiWorkspace'])->name('pages.seo-backlink');
    Route::post('/seo-backlink/{project}/generate', [ProjectController::class, 'generateAiContent'])->name('pages.seo-backlink.generate');
    Route::post('/projects/{project}/analyze-seo-backlink', [ProjectController::class, 'analyzeSeoBacklink'])
        ->name('pages.projects.seo-backlink.analyze');
    Route::get('/projects/{project}/analyze-seo-backlink/status', [ProjectController::class, 'seoBacklinkStatus'])
        ->name('pages.projects.seo-backlink.status');
    Route::post('/seo-backlink/preview/analyze', [ProjectController::class, 'analyzeSeoBacklinkPreview'])
        ->name('pages.seo-backlink.preview.analyze');
    Route::get('/seo-backlink/preview/status', [ProjectController::class, 'seoBacklinkPreviewStatus'])
        ->name('pages.seo-backlink.preview.status');
    Route::post('/projects/{project}/pagespeed/analyze', [ProjectController::class, 'analyzePageSpeed'])
        ->name('pages.projects.pagespeed.analyze');
    Route::get('/projects/{project}/pagespeed/status', [ProjectController::class, 'pageSpeedStatus'])
        ->name('pages.projects.pagespeed.status');
    Route::post('/projects/{project}/search-console/analyze', [ProjectController::class, 'analyzeSearchConsole'])
        ->name('pages.projects.search-console.analyze');
    Route::post('/projects/{project}/ga4/analyze', [ProjectController::class, 'analyzeGoogleAnalytics'])
        ->name('pages.projects.ga4.analyze');
    Route::get('/projects/{project}/seo-backlink/report/download', [ProjectController::class, 'downloadSeoBacklinkReport'])
        ->name('pages.projects.seo-backlink.report.download');
    Route::post('/projects/{project}/competitor-pagespeed/analyze', [ProjectController::class, 'analyzeCompetitorPageSpeed'])
        ->name('pages.projects.competitor-pagespeed.analyze');
    Route::get('/projects/{project}/competitor-pagespeed/status', [ProjectController::class, 'competitorPageSpeedStatus'])
        ->name('pages.projects.competitor-pagespeed.status');
    Route::post('/projects/{project}/manual-screenshot', [ProjectController::class, 'uploadManualScreenshot'])
        ->name('pages.projects.manual-screenshot.store');
    Route::delete('/projects/{project}/manual-screenshot', [ProjectController::class, 'deleteManualScreenshot'])
        ->name('pages.projects.manual-screenshot.destroy');
    Route::get('/projects/{project}/seo-proposal/download', [ProjectController::class, 'downloadSeoProposal'])
        ->name('pages.projects.seo-proposal.download');
    Route::post('/projects/{project}/competitors/select', [ProjectController::class, 'selectCompetitors'])
        ->name('pages.projects.competitors.select');
    Route::post('/projects/{project}/keywords/select', [ProjectController::class, 'selectKeywords'])
        ->name('pages.projects.keywords.select');

    // 6. Mockup
    Route::get('/mockup', [ProjectController::class, 'mockupTemplates'])->name('pages.mockup');

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
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');

    // 10. Proposal

    Route::post('/projects/{project}/proposal/generate', [ProjectController::class, 'generateProposal'])->name('pages.projects.proposal.generate');
    Route::get('/projects/{project}/proposal/status', [ProjectController::class, 'proposalStatus'])
        ->name('pages.projects.proposal.status');
    Route::resource('service-packages', \App\Http\Controllers\ServicePackageController::class)->except(['show']);

});

require __DIR__ . '/auth.php';