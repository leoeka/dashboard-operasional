<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\requestOrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
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
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('pages.projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('pages.projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('pages.projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('pages.projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('pages.projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('pages.projects.destroy');
    Route::post('/projects/{project}/tasks', [ProjectController::class, 'storeTask'])->name('pages.projects.tasks.store');
    Route::patch('/projects/{project}/tasks/{task}/toggle', [ProjectController::class, 'toggleTask'])->name('pages.projects.tasks.toggle');
    Route::delete('/projects/{project}/tasks/{task}', [ProjectController::class, 'destroyTask'])->name('pages.projects.tasks.destroy');
    Route::post('/projects/{project}/files', [ProjectController::class, 'storeFile'])->name('pages.projects.files.store');
    Route::delete('/projects/{project}/files/{file}', [ProjectController::class, 'destroyFile'])->name('pages.projects.files.destroy');
    Route::put('/projects/{project}/mockup', [ProjectController::class, 'addmockupTemplate'])
        ->name('pages.projects.mockup.add');

    // 5. AI Workspace
    Route::get('/ai-workspace', [ProjectController::class, 'aiWorkspace'])->name('pages.ai-workspace');
    Route::post('/ai-workspace/{project}/generate', [ProjectController::class, 'generateAiContent'])->name('pages.ai-workspace.generate');


    // 6. Mockup
    Route::get('/mockup', function () {
        return view('pages.mockup');
    })->name('pages.mockup');

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
    Route::get('/settings', [SettingController::class, 'index'])->name('pages.settings');

    // 10. Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');

    // 10. Proposal

    Route::post('/projects/{project}/proposal/generate', [ProjectController::class, 'generateProposal'])->name('pages.projects.proposal.generate');
    Route::resource('service-packages', \App\Http\Controllers\ServicePackageController::class)->except(['show']);
});


require __DIR__ . '/auth.php';