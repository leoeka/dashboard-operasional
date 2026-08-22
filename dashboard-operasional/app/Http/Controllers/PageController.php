<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function requestProject()
    {
        return view('pages.placeholder', [
            'title'       => 'Request Project',
            'description' => 'A form for submitting new client website project requests will appear here.',
        ]);
    }

    public function proposalAi()
    {
        return view('pages.placeholder', [
            'title'       => 'Proposal AI',
            'description' => 'An AI-powered automatic proposal generator will appear here.',
        ]);
    }

    public function mockupAi()
    {
        return view('pages.placeholder', [
            'title'       => 'Mockup AI',
            'description' => 'An AI-powered automatic mockup design generator will appear here.',
        ]);
    }

    public function websiteGenerator()
    {
        return view('pages.placeholder', [
            'title'       => 'Website Generator',
            'description' => 'A tool to automatically generate a website from an approved mockup will appear here.',
        ]);
    }

    public function aiWorkspace()
    {
        return view('pages.placeholder', [
            'title'       => 'AI Workspace',
            'description' => 'A workspace for monitoring running AI processes will appear here.',
        ]);
    }

    public function qa()
    {
        return view('pages.placeholder', [
            'title'       => 'QA',
            'description' => 'A list of project tests and quality assurance checklists will appear here.',
        ]);
    }

    public function reports()
    {
        return view('pages.placeholder', [
            'title'       => 'Reports',
            'description' => 'Project and team performance reports will appear here.',
        ]);
    }

    public function settings()
    {
        return view('pages.placeholder', [
            'title'       => 'Settings',
            'description' => 'Account and application settings will appear here.',
        ]);
    }
}
