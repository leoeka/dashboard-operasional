<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return view('pages.settings', [
            'appName' => 'SiteFlow',
            'appVersion' => 'v1.0.0',
            'appDescription' => 'An operational dashboard for managing website development services — from project requests and proposals to mockups, development, and financial reports.',
            'appEnvironment' => config('app.env'),
        ]);
    }
}