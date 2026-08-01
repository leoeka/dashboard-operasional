<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class requestOrderController extends Controller
{
    public function create()
    {
        return view('pages.request');
    }

    public function store(Request $request)
    {
        // 1. Validasi Input Data
        $validated = $request->validate([
            // Data Klien
            'client_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],

            // Data Proyek & Kebutuhan
            'business_type' => ['required', 'string', 'max:255'],
            'business_description' => ['required', 'string'],
            'business_goal' => ['required', 'string'],
            'website_type' => ['required', 'string', 'max:255'],

            // Data Assets (Contoh: File upload opsional)
            'assets' => ['nullable', 'array'],
            'assets.*' => ['file', 'mimes:jpg,jpeg,png,pdf,docx,zip', 'max:10240'], // Maks 10MB/file
        ]);

        // 2. Transaksi Database
        DB::beginTransaction();

        try {
            // A. Simpan Data Client
            $client = Client::create([
                'company_name' => $validated['company_name'],
                'contact_name' => $validated['client_name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $request->input('address'),
                'created_by' => Auth::id(), // Mengambil ID user yang sedang login
            ]);

            // B. Simpan Project
            $project = $client->projects()->create([
                'website_type' => $validated['website_type'],
                'status' => 'draft', // Set default status draft
                'created_by' => Auth::id(),
            ]);

            // C. Simpan Requirements (Terhubung ke Project)
            $project->requirement()->create([
                'business_type' => $validated['business_type'],
                'business_description' => $validated['business_description'],
                'business_goal' => $validated['business_goal'],
            ]);

            // D. Simpan Assets (Jika ada lampiran file)
            if ($request->hasFile('assets')) {
                foreach ($request->file('assets') as $file) {
                    $path = $file->store('project-assets', 'public');

                    $project->assets()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_type' => $file->getClientMimeType(),
                    ]);
                }
            }

            // Commit jika semua proses berhasil
            DB::commit();

            return redirect()->route('projects.index')
                ->with('success', 'Draft proyek berhasil disimpan!');

        } catch (\Exception $e) {
            // Rollback jika terjadi kesalahan/error
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }
}
