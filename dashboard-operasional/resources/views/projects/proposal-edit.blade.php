@extends('layouts.app')
@section('title', $project->name)

@section('content')<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">

        <!-- Header Halaman -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <a href="{{ route('pages.projects.show', $project) }}"
                    class="text-xs text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-1">
                    <i class='bx bx-arrow-back'></i> Kembali ke Detail Project
                </a>
                <h1 class="text-xl font-bold text-slate-800">Edit & Review Proposal AI</h1>
                <p class="text-xs text-slate-500">Sesuaikan template atau isi proposal jika klien menginginkan perubahan.
                </p>
            </div>

            <!-- Tombol Cetak / Download -->
            <div class="flex gap-2">
                <a href="{{ route('pages.projects.proposal.generate', $project) }}" download
                    class="inline-flex items-center gap-2 bg-emerald-600 text-white text-xs font-semibold px-4 py-2 rounded-lg hover:bg-emerald-700 transition shadow-sm">
                    <i class='bx bx-download text-base'></i> Download PDF
                </a>
            </div>
        </div>

        <!-- Grid Split Screen (1: Edit Form, 2: Preview PDF) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[80vh]">

            <!-- Side Panel: Form Edit (35% Lebar) -->
            <div
                class="lg:col-span-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm overflow-y-auto flex flex-col justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Pengaturan Proposal
                    </h2>

                    <!-- Box Informasi Rekomendasi AI -->
                    {{-- @if ($aiReasoning)
                        <div class="mb-5 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs">
                            <span class="font-bold text-blue-700 block mb-1">🤖 Rekomendasi AI:</span>
                            <p class="text-blue-600">{{ $aiReasoning }}</p>
                        </div>
                    @endif --}}

                    <form action="{{ route('pages.projects.proposal.update', $project) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <!-- Pilih Mockup Template -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Template Mockup</label>
                            <select name="mockup_template_id"
                                class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-xs rounded-lg p-2.5 outline-none focus:border-blue-500">
                                @foreach ($templates as $tpl)
                                    <option value="{{ $tpl->id }}" @selected($project->mockup_template_id == $tpl->id)>
                                        {{ $tpl->name }} ({{ $tpl->category }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Catatan Tambahan Proposal (Opsional) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan
                                Klien</label>
                            <textarea name="proposal_notes" rows="4" placeholder="Tambahkan catatan khusus dari klien jika ada..."
                                class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-xs rounded-lg p-2.5 outline-none focus:border-blue-500">{{ old('proposal_notes', $project->proposal_notes ?? '') }}</textarea>
                        </div>

                        <button type="submit"
                            class="w-full grad-blue text-white text-xs font-semibold py-2.5 rounded-lg hover:opacity-90 transition">
                            <i class='bx bx-refresh'></i> Simpan & Update Preview
                        </button>
                    </form>
                </div>

                <!-- Footer Informasi Proyek -->
                <div class="pt-4 border-t border-slate-100 text-xs text-slate-400">
                    <p>Client: <strong class="text-slate-600">{{ $project->client_name }}</strong></p>
                    <p>Tipe Project: <strong class="text-slate-600">{{ $project->type }}</strong></p>
                </div>
            </div>

            <!-- Live Preview PDF (65% Lebar) -->
            <!-- Live Preview PDF Fisik dari Storage -->
            <div class="lg:col-span-8 bg-slate-100 rounded-xl border border-slate-200 overflow-hidden shadow-inner">
                <iframe src="{{ route('pages.projects.proposal.stream', $project) }}"
                    class="w-full h-full border-0"></iframe>
            </div>
        </div>
    </div>
@endsection
