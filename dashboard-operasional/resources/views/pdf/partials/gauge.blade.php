{{--
    Partial: 1 lingkaran skor gaya PageSpeed Insights (merah/oranye/hijau),
    dibuat pakai SVG murni — BUKAN gambar/screenshot, jadi angkanya
    dijamin presisi 100% (diambil langsung dari variabel yang sama
    dengan yang dipakai di tabel, tidak mungkin meleset).

    Pakai dengan: @include('pdf.partials.gauge', ['score' => 71, 'label' => 'Performance'])
    $score null/tidak ada -> ditampilkan sebagai "-" abu-abu.
--}}
@php
    $score = $score ?? null;
    $label = $label ?? '';

    if ($score === null) {
        $color = '#cbd5e1';
    } elseif ($score >= 90) {
        $color = '#0cce6b'; // hijau — sama seperti skema warna resmi PageSpeed
    } elseif ($score >= 50) {
        $color = '#ffa400'; // oranye
    } else {
        $color = '#ff4e42'; // merah
    }

    $radius = 26;
    $circumference = 2 * M_PI * $radius;
    $offset = $score === null ? 0 : $circumference * (1 - $score / 100);
@endphp
<div style="display:inline-block; text-align:center; width:72px; margin: 0 3px;">
    <svg width="64" height="64" viewBox="0 0 64 64">
        <circle cx="32" cy="32" r="{{ $radius }}" fill="none" stroke="#e2e8f0" stroke-width="6"></circle>
        @if ($score !== null)
            <circle cx="32" cy="32" r="{{ $radius }}" fill="none" stroke="{{ $color }}" stroke-width="6"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $offset }}"
                stroke-linecap="round"
                transform="rotate(-90 32 32)"></circle>
        @endif
        <text x="32" y="37" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="16" font-weight="bold" fill="{{ $color }}">{{ $score ?? '-' }}</text>
    </svg>
    <div style="font-size:8px; color:#64748b; margin-top:2px;">{{ $label }}</div>
</div>