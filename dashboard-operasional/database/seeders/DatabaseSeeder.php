<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            ['code' => 'ABC-0142', 'client_name' => 'PT ABC', 'type' => 'Company Profile', 'status' => 'proposal', 'progress' => 20, 'value' => 12000000, 'deadline' => now()->addDays(5)],
            ['code' => 'XYZ-0138', 'client_name' => 'PT XYZ', 'type' => 'E-commerce', 'status' => 'mockup', 'progress' => 40, 'value' => 18500000, 'deadline' => now()->addDays(8)],
            ['code' => 'DEF-0151', 'client_name' => 'PT DEF', 'type' => 'Landing Page', 'status' => 'active', 'progress' => 100, 'value' => 6000000, 'deadline' => now()->subDays(2)],
            ['code' => 'GHI-0155', 'client_name' => 'CV Ghifari', 'type' => 'Company Profile', 'status' => 'development', 'progress' => 55, 'value' => 9500000, 'deadline' => now()->addDays(12)],
            ['code' => 'JKL-0159', 'client_name' => 'Kopi Lokal Indonesia', 'type' => 'E-commerce', 'status' => 'request', 'progress' => 5, 'value' => 22000000, 'deadline' => now()->addDays(20)],
        ];

        foreach ($projects as $data) {
            $project = Project::create($data);

            Proposal::create([
                'project_id' => $project->id,
                'client_name' => $project->client_name,
                'status' => $project->status === 'request' ? 'pending' : 'approved',
                'summary' => 'Proposal awal untuk ' . $project->type,
            ]);
        }

        $activities = [
            ['client_name' => 'PT ABC', 'action' => 'Proposal Generated', 'status' => 'approved'],
            ['client_name' => 'PT XYZ', 'action' => 'Waiting Approval', 'status' => 'pending'],
            ['client_name' => 'PT DEF', 'action' => 'Website Generated', 'status' => 'success'],
            ['client_name' => 'CV Ghifari', 'action' => 'Mockup Direview Klien', 'status' => 'pending'],
            ['client_name' => 'Kopi Lokal Indonesia', 'action' => 'Request Project Diterima', 'status' => 'approved'],
        ];

        foreach ($activities as $data) {
            ActivityLog::create($data);
        }
    }
}
