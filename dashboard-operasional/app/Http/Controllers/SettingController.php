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
            'appDescription' => 'Dashboard operasional untuk pengelolaan jasa pembuatan website — mulai dari request project, proposal, mockup, development, hingga laporan keuangan.',
            'appEnvironment' => config('app.env'),
        ]);
    }
}