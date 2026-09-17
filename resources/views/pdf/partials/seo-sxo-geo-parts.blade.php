{{-- Rincian sub-skor satu lapis. $parts = list<{label,score,weight}> --}}
<table class="data-table">
    <tr><th>Komponen skor</th><th class="num">Nilai</th><th class="num">Bobot</th></tr>
    @foreach ($parts as $part)
        <tr>
            <td>{{ $part['label'] }}</td>
            <td class="num">{{ $part['score'] === null ? 'belum dianalisis' : $part['score'] . '/100' }}</td>
            <td class="num">{{ (int) round($part['weight'] * 100) }}%</td>
        </tr>
    @endforeach
</table>
