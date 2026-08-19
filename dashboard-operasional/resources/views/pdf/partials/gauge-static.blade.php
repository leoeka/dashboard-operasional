{{--
Partial KHUSUS PDF: 1 lingkaran skor (merah/oranye/hijau, gaya
PageSpeed) — versi lingkaran SOLID (div+border-radius), BUKAN SVG.

KENAPA ADA 2 FILE GAUGE (ini + pdf/partials/gauge.blade.php):
dompdf (mesin generate PDF) TIDAK SANGGUP render teknik SVG
progress-ring (stroke-dasharray) yang dipakai di gauge.blade.php —
walau itu tampil sempurna di browser (halaman Workspace). Jadi
dipisah: gauge.blade.php (SVG) buat Workspace, gauge-static.blade.php
(lingkaran solid biasa, ini) KHUSUS buat template PDF.

JANGAN diganti balik ke SVG di sini — akan pecah lagi di PDF.

Pakai dengan: @include('pdf.partials.gauge-static', ['score' => 71, 'label' => 'Performance'])
$score null/tidak ada -> ditampilkan sebagai "-" abu-abu.
--}}
@php
    $score = $score ?? null;
    $label = $label ?? '';

    if ($score === null) {
        $color = '#cbd5e1';
    } elseif ($score >= 90) {
        $color = '#0cce6b';
    } elseif ($score >= 50) {
        $color = '#ffa400';
    } else {
        $color = '#ff4e42';
    }
@endphp
<div style="display:inline-block; text-align:center; width:70px; margin: 0 3px;">
    <div
        style="width:56px; height:56px; border-radius:50%; background-color:{{ $color }}; margin:0 auto; text-align:center; line-height:56px; font-family: Helvetica, Arial, sans-serif;">
        <span style="color:#ffffff; font-size:16px; font-weight:bold;">{{ $score ?? '-' }}</span>
    </div>
    <div style="font-size:8px; color:#64748b; margin-top:4px; font-family: Helvetica, Arial, sans-serif;">{{ $label }}
    </div>
</div>