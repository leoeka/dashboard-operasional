@php
    $semrushReport = $seo['semrush_report'] ?? [];
    $semrushMetrics = $semrushReport['metrics'] ?? [];
    $semrushFields = [
        'authority_score' => ['Authority Score', 100],
        'ai_visibility_score' => ['AI Visibility Score', null],
        'organic_traffic' => ['Organic Traffic', null],
        'organic_keywords' => ['Organic Keywords', null],
        'paid_traffic' => ['Paid Traffic', null],
        'paid_keywords' => ['Paid Keywords', null],
        'referring_domains' => ['Referring Domains', null],
        'backlinks' => ['Backlinks', null],
        'traffic_share' => ['Traffic Share (%)', 100],
    ];
@endphp

<x-card class="mb-6">
    <h2 class="font-semibold text-slate-800">Data Semrush untuk laporan SEO</h2>
    <p class="text-xs text-slate-600 mt-1 mb-4">
        Unggah screenshot overview Semrush dan masukkan angka secara manual. Screenshot tidak dibaca otomatis.
    </p>

    @if ($resolvedUrl)
        <a href="https://www.semrush.com/analytics/overview/?q={{ urlencode(parse_url($resolvedUrl, PHP_URL_HOST)) }}&searchType=domain"
            target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-xs text-brand-600 hover:underline">
            Buka Semrush untuk domain ini &rarr;
        </a>
    @endif

    @if (!empty($seo['manual_screenshots']['own_semrush']))
        <div class="mt-2">
            <img src="{{ Storage::url($seo['manual_screenshots']['own_semrush']) }}" alt="Screenshot Semrush"
                class="max-w-xl max-h-72 object-contain border border-slate-200 rounded-md">
            <form action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}" method="POST" class="mt-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="target" value="own_semrush">
                <input type="hidden" name="return_tab" value="traffic">
                <button type="submit" class="min-h-11 text-xs text-red-700 hover:underline">Hapus screenshot untuk unggah ulang</button>
            </form>
        </div>
    @else
        <form action="{{ route('pages.projects.manual-screenshot.store', $project) }}" method="POST"
            enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
            @csrf
            <input type="hidden" name="target" value="own_semrush">
            <input type="hidden" name="return_tab" value="traffic">
            <input type="file" name="screenshot" accept="image/*" required class="text-xs">
            <button type="submit" class="min-h-11 text-xs bg-brand-600 text-white px-3 py-2 rounded-md">Unggah screenshot</button>
        </form>
    @endif

    <form action="{{ route('pages.projects.seo-analysis.semrush.store', $project) }}" method="POST"
        class="mt-5 border-t border-slate-100 pt-4">
        @csrf
        <label class="block text-xs font-semibold text-slate-700 mb-1" for="semrush_as_of">Tanggal data Semrush</label>
        <input id="semrush_as_of" type="date" name="as_of" value="{{ old('as_of', $semrushReport['as_of'] ?? '') }}"
            class="min-h-11 border border-slate-300 rounded-md px-3 py-2 text-sm mb-4">

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($semrushFields as $key => [$label, $max])
                <label class="block text-xs text-slate-700">{{ $label }}
                    <input type="number" min="0" @if ($max !== null) max="{{ $max }}" @endif
                        step="{{ $key === 'authority_score' ? '0.1' : '1' }}" name="metrics[{{ $key }}]"
                        value="{{ old('metrics.' . $key, $semrushMetrics[$key] ?? '') }}"
                        class="min-h-11 block w-full mt-1 border border-slate-300 rounded-md px-3 py-2 text-sm">
                </label>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <label class="block text-xs text-slate-700">Visibilitas AI — satu platform per baris: <span class="font-mono">Platform|Mentions|Cited pages</span>
                <textarea name="ai_platforms_text" rows="4"
                    class="block w-full mt-1 border border-slate-300 rounded-md px-3 py-2 text-sm"
                    placeholder="ChatGPT|7|28&#10;Google AI Overview|0|28&#10;Gemini|10|7">{{ old('ai_platforms_text', collect($semrushReport['ai_platforms'] ?? [])->map(fn ($row) => ($row['label'] ?? '') . '|' . ($row['first'] ?? 0) . '|' . ($row['second'] ?? 0))->implode("\n")) }}</textarea>
            </label>
            <label class="block text-xs text-slate-700">Distribusi negara — satu negara per baris: <span class="font-mono">Negara|Traffic|Keywords</span>
                <textarea name="countries_text" rows="4"
                    class="block w-full mt-1 border border-slate-300 rounded-md px-3 py-2 text-sm"
                    placeholder="Indonesia|2900|381&#10;Australia|598|203">{{ old('countries_text', collect($semrushReport['countries'] ?? [])->map(fn ($row) => ($row['label'] ?? '') . '|' . ($row['first'] ?? 0) . '|' . ($row['second'] ?? 0))->implode("\n")) }}</textarea>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <label class="block text-xs text-slate-700">Ringkasan tren
                <textarea name="trend_summary" rows="3" maxlength="2500"
                    class="block w-full mt-1 border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('trend_summary', $semrushReport['trend_summary'] ?? '') }}</textarea>
            </label>
            <label class="block text-xs text-slate-700">Poin penting / interpretasi
                <textarea name="key_points" rows="3" maxlength="2500"
                    class="block w-full mt-1 border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('key_points', $semrushReport['key_points'] ?? '') }}</textarea>
            </label>
        </div>
        <label class="block text-xs text-slate-700 mt-4">Kesimpulan dan saran tindak lanjut
            <textarea name="recommendations" rows="3" maxlength="2500"
                class="block w-full mt-1 border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('recommendations', $semrushReport['recommendations'] ?? '') }}</textarea>
        </label>
        <button type="submit" class="min-h-11 mt-4 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md">
            Simpan data Semrush
        </button>
    </form>
</x-card>

<a href="{{ route('pages.projects.seo-analysis.report.download', $project) }}"
    class="min-h-11 mb-4 inline-flex items-center gap-2 bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-md hover:bg-brand-700">
    <i class='bx bx-download'></i> Unduh Analysis SEO Page (PDF)
</a>
