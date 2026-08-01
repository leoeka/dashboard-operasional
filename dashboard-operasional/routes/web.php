<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\requestOrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;

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
    Route::get('/projects', function () {
        return view('pages.projects');
    })->name('pages.projects');


    // 5. AI Workspace
    Route::get('/ai-workspace', function () {
        return view('pages.ai-workspace');
    })->name('pages.ai-workspace');

    // 6. Mockup
    Route::get('/mockup', function () {
        return view('pages.mockup');
    })->name('pages.mockup');

    // 7. Website
    Route::get('/website', function () {
        return view('pages.website');
    })->name('pages.website');

    // 8. Finance
    Route::get('/finance', function () {
        return view('pages.finance');
    })->name('pages.finance');

    // 9. Reports
    Route::get('/reports', function () {
        return view('pages.reports');
    })->name('pages.reports');

    // 10. Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('pages.settings');


    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');
});


require __DIR__ . '/auth.php';