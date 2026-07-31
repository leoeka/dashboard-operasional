<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - SiteFlow
|--------------------------------------------------------------------------
| Mengikuti struktur sidebar:
| Dashboard, Request Project, Proposal AI, Mockup AI, Website Generator,
| Projects, AI Workspace, QA, Reports, Settings
*/

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

Route::get('/request-project', [PageController::class, 'requestProject'])->name('request-project');
Route::get('/proposal-ai', [PageController::class, 'proposalAi'])->name('proposal-ai');
Route::get('/mockup-ai', [PageController::class, 'mockupAi'])->name('mockup-ai');
Route::get('/website-generator', [PageController::class, 'websiteGenerator'])->name('website-generator');
Route::get('/ai-workspace', [PageController::class, 'aiWorkspace'])->name('ai-workspace');
Route::get('/qa', [PageController::class, 'qa'])->name('qa');
Route::get('/reports', [PageController::class, 'reports'])->name('reports');
Route::get('/settings', [PageController::class, 'settings'])->name('settings');
