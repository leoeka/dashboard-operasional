@php
    $geoReport = app(\App\Services\SeoSxoGeoReportService::class)->assemble($project);
    $geoScores = $geoReport['scores'];
    $geoStatusClass = [
        'good' => 'text-emerald-700',
        'needs_improvement' => 'text-amber-700',
        'poor' => 'text-red-700',
        'unknown' => 'text-slate-600',
    ];
    $geoStatusLabel = [
        'good' => 'Baik',
        'needs_improvement' => 'Perlu perbaikan',
        'poor' => 'Kurang',
        'unknown' => 'Belum dianalisis',
    ];
@endphp

<x-card class="mb-6">
    <div class="mb-3">
        <h2 class="font-semibold text-slate-800">SEO / SXO / GEO</h2>
        <p class="text-xs text-slate-500 mt-1">
            Kesehatan tiga lapis: ditemukan di Google (SEO), pengalaman pengunjung dari
            pencarian (SXO), siap dibaca &amp; dikutip mesin jawaban AI (GEO). Skor
            dihitung dari analisis yang sudah dijalankan di halaman ini.
        </p>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-4">
        @foreach (['seo' => 'SEO', 'sxo' => 'SXO', 'geo' => 'GEO'] as $k => $name)
            @php $sc = $geoScores[$k]; @endphp
            <div class="rounded-lg border border-slate-200 p-3 text-center">
                <div class="text-xs font-semibold tracking-wide text-slate-600">{{ $name }}
                </div>
                <div class="text-2xl font-bold {{ $geoStatusClass[$sc['status']] }}">
                    {{ $sc['value'] ?? '-' }}<span
                        class="text-xs text-slate-600">{{ $sc['value'] === null ? '' : '/100' }}</span>
                </div>
                <div class="text-xs {{ $geoStatusClass[$sc['status']] }}">
                    {{ $geoStatusLabel[$sc['status']] }}</div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button"
            data-geo-analyze="{{ route('pages.projects.ai-crawler-access.analyze', $project) }}"
            data-geo-status="{{ route('pages.projects.ai-crawler-access.status', $project) }}"
            class="min-h-11 geo-btn text-xs font-semibold px-3 py-2 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 disabled:opacity-50">
            <i class='bx bx-bot'></i> Cek akses AI crawler
        </button>
        <button type="button"
            data-geo-analyze="{{ route('pages.projects.structured-data.analyze', $project) }}"
            data-geo-status="{{ route('pages.projects.structured-data.status', $project) }}"
            class="min-h-11 geo-btn text-xs font-semibold px-3 py-2 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 disabled:opacity-50">
            <i class='bx bx-code-curly'></i> Cek schema (structured data)
        </button>
        <button type="button"
            data-geo-analyze="{{ route('pages.projects.ctr-gap.analyze', $project) }}"
            data-geo-status="{{ route('pages.projects.ctr-gap.status', $project) }}"
            class="min-h-11 geo-btn text-xs font-semibold px-3 py-2 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 disabled:opacity-50">
            <i class='bx bx-line-chart-down'></i> Cek CTR gap
        </button>
        <button type="button"
            data-geo-analyze="{{ route('pages.projects.onpage-audit.analyze', $project) }}"
            data-geo-status="{{ route('pages.projects.onpage-audit.status', $project) }}"
            class="min-h-11 geo-btn text-xs font-semibold px-3 py-2 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 disabled:opacity-50">
            <i class='bx bx-file'></i> Audit on-page
        </button>
        <button type="button"
            data-geo-analyze="{{ route('pages.projects.technical-seo.analyze', $project) }}"
            data-geo-status="{{ route('pages.projects.technical-seo.status', $project) }}"
            class="min-h-11 geo-btn text-xs font-semibold px-3 py-2 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 disabled:opacity-50">
            <i class='bx bx-wrench'></i> Cek SEO teknis
        </button>
        <button type="button"
            data-geo-analyze="{{ route('pages.projects.content-extractability.analyze', $project) }}"
            data-geo-status="{{ route('pages.projects.content-extractability.status', $project) }}"
            class="min-h-11 geo-btn text-xs font-semibold px-3 py-2 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 disabled:opacity-50">
            <i class='bx bx-bulb'></i> Cek konten (AI)
        </button>
        <a href="{{ route('pages.projects.seo-sxo-geo.report.download', $project) }}"
            class="min-h-11 text-xs font-semibold px-3 py-2 rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50">
            <i class='bx bx-download'></i> Download laporan SEO/SXO/GEO
        </a>
    </div>
    <p class="text-xs text-slate-600 mt-2">
        Skor SXO (engagement) &amp; CTR gap ikut terisi setelah analisis Search Console
        dan GA4 di langkah <span class="font-semibold">Laporan traffic</span> dijalankan.
    </p>
    <p class="geo-msg hidden text-xs mt-2"></p>

    <script>
        (function() {
            const root = document.currentScript.parentElement;
            const msg = root.querySelector('.geo-msg');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            root.querySelectorAll('.geo-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const analyzeUrl = btn.dataset.geoAnalyze;
                    const statusUrl = btn.dataset.geoStatus;
                    const label = btn.innerHTML;

                    btn.disabled = true;
                    btn.innerHTML = 'Memproses…';
                    msg.className = 'geo-msg text-xs mt-2 text-slate-500';
                    msg.textContent = 'Analisis dimulai…';

                    fetch(analyzeUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrf
                            },
                        })
                        .then((r) => r.json())
                        .then(() => {
                            const poll = setInterval(() => {
                                fetch(statusUrl, {
                                        headers: {
                                            Accept: 'application/json'
                                        }
                                    })
                                    .then((r) => r.json())
                                    .then((s) => {
                                        if (s.message) msg.textContent = s.message;
                                        if (s.status === 'done') {
                                            clearInterval(poll);
                                            location.reload();
                                        } else if (s.status === 'failed') {
                                            clearInterval(poll);
                                            msg.className =
                                                'geo-msg text-xs mt-2 text-red-700';
                                            btn.disabled = false;
                                            btn.innerHTML = label;
                                        }
                                    });
                            }, 2000);
                        })
                        .catch(() => {
                            msg.className = 'geo-msg text-xs mt-2 text-red-700';
                            msg.textContent = 'Gagal memulai analisis.';
                            btn.disabled = false;
                            btn.innerHTML = label;
                        });
                });
            });
        })();
    </script>
</x-card>
