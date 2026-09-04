<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Front-end shell only — kept because the "Mockup" nav item may be reused
 * later, but no longer wired to the ZipWP API (superseded by the Gemini/
 * GPT/Claude pipeline in WebsiteBuilderController). Always renders an
 * empty template list.
 */
class MockupController extends Controller
{
    public function index(Request $request)
    {
        return view('pages.mockup', [
            'templates' => [],
            'currentPage' => 1,
            'lastPage' => 1,
            'totalItems' => 0,
        ]);
    }
}
