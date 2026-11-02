<?php

use App\Support\MockupRules;

it('menyusun berkas aturan untuk ai: aturan mockup plus lampiran K M P dari dokumen desain', function () {
    $isi = MockupRules::load();

    expect($isi)->toContain('# ATURAN-MOCKUP v1 (2026-10-22)')
        ->toContain('Metode dua ronde')
        ->toContain('| K-1 |')
        ->toContain('| M-1 |')
        ->toContain('P-1 Tiap opsi wajib menulis satu kalimat idenya');
});
