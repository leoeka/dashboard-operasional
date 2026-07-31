<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PageController;
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
    Route::get('/crm', function () {
        return view('pages.crm');
    })->name('pages.crm');

    // 3. Project
    Route::get('/projects', function () {
        return view('pages.projects');
    })->name('pages.projects');

    // 4. Request Order
    Route::get('/request-order', function () {
        return view('pages.request');
    })->name('pages.request');

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
    Route::post('/settings/language', [SettingController::class, 'updateLanguage'])->name('pages.settings.language');


    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');
});


require __DIR__ . '/auth.php';