<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            [
                'username' => 'exitobali',
            ],
            [
                'name' => 'exitobali',
                'email' => 'exitobali@example.com',
                'password' => Hash::make('exitobali123'),
            ]
        );
    }
}
