<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
            // atau dengan opsi khusus:
            // new Middleware('auth', only: ['index']),
        ];
    }
    public function index()
    {
        $totalProject = Project::count();
        $proposalPending = Proposal::where('status', 'pending')->count();
        $websiteActive = Project::where('status', 'active')->count();
        $upcomingDeadline = Project::whereNotNull('deadline')
            ->whereBetween('deadline', [now(), now()->addDays(14)])
            ->count();

        $recentActivities = ActivityLog::latest()->take(6)->get();

        return view('dashboard.index', compact(
            'totalProject',
            'proposalPending',
            'websiteActive',
            'upcomingDeadline',
            'recentActivities',
        ));
    }
}
