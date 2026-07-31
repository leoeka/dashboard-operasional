<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return view('pages.settings', [
            'currentLocale' => session('locale', config('app.locale')),
        ]);
    }

    public function updateLanguage(Request $request)
    {
        $request->validate([
            'locale' => 'required|in:id,en',
        ]);

        session(['locale' => $request->input('locale')]);

        return back()->with('success', __('Bahasa berhasil diubah.'));
    }
}