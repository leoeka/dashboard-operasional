<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function requestProject()
    {
        return view('pages.placeholder', [
            'title'       => 'Request Project',
            'description' => 'Form pengajuan proyek website baru dari klien akan tampil di sini.',
        ]);
    }

    public function proposalAi()
    {
        return view('pages.placeholder', [
            'title'       => 'Proposal AI',
            'description' => 'Alat pembuat proposal otomatis berbasis AI akan tampil di sini.',
        ]);
    }

    public function mockupAi()
    {
        return view('pages.placeholder', [
            'title'       => 'Mockup AI',
            'description' => 'Alat pembuat mockup desain otomatis berbasis AI akan tampil di sini.',
        ]);
    }

    public function websiteGenerator()
    {
        return view('pages.placeholder', [
            'title'       => 'Website Generator',
            'description' => 'Alat generate website otomatis dari mockup yang disetujui akan tampil di sini.',
        ]);
    }

    public function aiWorkspace()
    {
        return view('pages.placeholder', [
            'title'       => 'AI Workspace',
            'description' => 'Ruang kerja untuk memantau proses AI berjalan akan tampil di sini.',
        ]);
    }

    public function qa()
    {
        return view('pages.placeholder', [
            'title'       => 'QA',
            'description' => 'Daftar pengujian dan checklist quality assurance proyek akan tampil di sini.',
        ]);
    }

    public function reports()
    {
        return view('pages.placeholder', [
            'title'       => 'Reports',
            'description' => 'Laporan performa proyek dan tim akan tampil di sini.',
        ]);
    }

    public function settings()
    {
        return view('pages.placeholder', [
            'title'       => 'Settings',
            'description' => 'Pengaturan akun dan aplikasi akan tampil di sini.',
        ]);
    }
}
