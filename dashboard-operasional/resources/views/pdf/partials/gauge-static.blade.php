{{--
Partial SPECIFIC TO PDF: a single score circle (red/orange/green,
PageSpeed style) — SOLID circle version (div+border-radius), NOT SVG.

WHY THERE ARE 2 GAUGE FILES (this one + pdf/partials/gauge.blade.php):
dompdf (the PDF generation engine) CANNOT render the SVG
progress-ring technique (stroke-dasharray) used in gauge.blade.php —
even though it renders perfectly in the browser (Workspace pages). So
it's split: gauge.blade.php (SVG) for the Workspace, gauge-static.blade.php
(plain solid circle, this one) SPECIFICALLY for the PDF template.

DO NOT switch this back to SVG — it will break again in the PDF.

Usage: @include('pdf.partials.gauge-static', ['score' => 71, 'label' => 'Performance'])
$score null/missing -> shown as a gray "-".
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