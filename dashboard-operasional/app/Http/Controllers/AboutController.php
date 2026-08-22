<?php

namespace App\Http\Controllers;

class AboutController extends Controller
{
    public function index()
    {
        return view('pages.about', [
            'appName' => 'SiteFlow',
            'appVersion' => 'v1.0.0',
            'appDescription' => 'An operational dashboard for managing website development services — from project requests and proposals to mockups, development, and financial reports.',
            'appEnvironment' => config('app.env'),
        ]);
    }
}
