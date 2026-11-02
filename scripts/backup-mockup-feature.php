<?php
// Helper satu-kali: bungkus daftar berkas jadi backup_fitur_mockup.zip + MANIFEST.
// Dipakai ulang lewat: php scripts/backup-mockup-feature.php <daftar-berkas>
// Skrip ini tidak mengubah satu pun berkas sumber; dia hanya membaca.

$listFile = $argv[1] ?? null;
if (!$listFile || !is_file($listFile)) {
    fwrite(STDERR, "Pakai: php scripts/backup-mockup-feature.php <berkas-daftar>\n");
    exit(1);
}

$repo = dirname(__DIR__);
$paths = array_values(array_filter(array_map('trim', (array) file($listFile, FILE_IGNORE_NEW_LINES))));

$zipPath = $repo . '/backup_fitur_mockup.zip';
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "ZIP tidak dapat dibuat: {$zipPath}\n");
    exit(1);
}

$hash = trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD'));
$branch = trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse --abbrev-ref HEAD'));

$manifest = [];
$missing = [];
$totalBytes = 0;

foreach ($paths as $relative) {
    $absolute = $repo . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($absolute)) {
        $missing[] = $relative;
        continue;
    }
    $zip->addFile($absolute, $relative);
    $size = filesize($absolute);
    $totalBytes += $size;
    $manifest[] = sprintf('%9d  %s', $size, $relative);
}

$header = [
    'Backup fitur mockup SiteFlow',
    'Dibuat      : ' . date('Y-m-d H:i:s'),
    'Commit      : ' . $hash . ' (' . $branch . ')',
    'Jumlah file : ' . count($manifest) . ' berkas, ' . $totalBytes . ' byte',
    '',
    'Cara memakai: unzip backup_fitur_mockup.zip -d nama-folder',
    'Isi: kode generator mockup/website lama (service, controller, view, support, jobs,',
    'migrasi, tes, dokumen .docs) + routes/web.php dan config yang dipakai bersama.',
    '',
    'DAFTAR ISI',
    str_repeat('-', 60),
];

$zip->addFromString('MANIFEST.txt', implode("\n", $header) . "\n" . implode("\n", $manifest) . "\n");
$zip->close();

echo 'Berkas masuk : ' . count($manifest) . "\n";
echo 'Ukuran ZIP   : ' . filesize($zipPath) . " byte\n";
echo 'Commit       : ' . $hash . "\n";
if ($missing) {
    echo 'TIDAK DITEMUKAN: ' . implode(', ', $missing) . "\n";
}
