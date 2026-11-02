<?php

namespace App\Services;

use App\Models\WpBuild;
use App\Support\MockupWorkspace;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Meja Periksa (Quality Gate) & Pengemasan ZIP Tema WordPress.
 *
 * Menerapkan kontrak bagian 6 rancangan builder 2026-10-21:
 * - Pemeriksaan sintaks php -l pada seluruh berkas templat PHP.
 * - Berkas wajib tema WordPress lengkap dan kepala style.css sah.
 * - Nol penanda __EXITO_ tersisa di seluruh berkas tema.
 * - Menu WordPress terdaftar pada functions.php.
 * - K-9: Tanpa skrip eksternal atau iframe mencurigakan.
 * - M-11: Ukuran berkas di bawah ambang (style.css <= 60KB, templat <= 120KB).
 * - M-1: Pengukuran kontras warna WCAG.
 *
 * Bila lolos meja periksa:
 * - Seluruh berkas tema dikemas menjadi theme-install.zip yang siap di-upload ke WordPress.
 * - Status build diubah menjadi 'ready'.
 *
 * Bila gagal:
 * - Status build diubah menjadi 'inspection_failed' dan daftar rincian kegagalan
 *   disimpan pada kolom inspection_results.
 */
class WpThemeInspector
{
    public function __construct(
        private ?MockupWorkspace $workspace = null,
    ) {
        $this->workspace = $this->workspace ?? new MockupWorkspace();
    }

    public function inspectAndPackage(WpBuild $build): WpBuild
    {
        $themeDir = $this->workspace->buildPath($build);

        if (!is_dir($themeDir)) {
            throw new RuntimeException('Direktori tema build #' . $build->id . ' tidak ditemukan: ' . $themeDir);
        }

        $checks = [];
        $errors = [];

        // 1. Berkas wajib tema WordPress
        $requiredFilesCheck = $this->checkRequiredFiles($themeDir);
        $checks['required_files'] = $requiredFilesCheck['passed'];
        if (!empty($requiredFilesCheck['errors'])) {
            $errors = array_merge($errors, $requiredFilesCheck['errors']);
        }

        // 2. Header style.css sah
        $styleHeaderCheck = $this->checkStyleHeader($themeDir);
        $checks['style_header'] = $styleHeaderCheck['passed'];
        if (!empty($styleHeaderCheck['errors'])) {
            $errors = array_merge($errors, $styleHeaderCheck['errors']);
        }

        // 3. Sintaks PHP (php -l)
        $syntaxCheck = $this->checkSyntaxLint($themeDir);
        $checks['syntax_lint'] = $syntaxCheck['passed'];
        if (!empty($syntaxCheck['errors'])) {
            $errors = array_merge($errors, $syntaxCheck['errors']);
        }

        // 4. Nol penanda __EXITO_
        $markersCheck = $this->checkZeroMarkers($themeDir);
        $checks['zero_markers'] = $markersCheck['passed'];
        if (!empty($markersCheck['errors'])) {
            $errors = array_merge($errors, $markersCheck['errors']);
        }

        // 5. Registrasi menu WordPress
        $menuCheck = $this->checkMenuRegistration($themeDir);
        $checks['menu_locations'] = $menuCheck['passed'];
        if (!empty($menuCheck['errors'])) {
            $errors = array_merge($errors, $menuCheck['errors']);
        }

        // 6. K-9: Tanpa skrip eksternal berbahaya atau iframe
        $embedsCheck = $this->checkNoExternalEmbeds($themeDir);
        $checks['no_external_embeds'] = $embedsCheck['passed'];
        if (!empty($embedsCheck['errors'])) {
            $errors = array_merge($errors, $embedsCheck['errors']);
        }

        // 7. M-11: Batas ukuran berkas
        $sizeCheck = $this->checkFileSizes($themeDir);
        $checks['file_size'] = $sizeCheck['passed'];
        if (!empty($sizeCheck['errors'])) {
            $errors = array_merge($errors, $sizeCheck['errors']);
        }

        // 8. M-1 & M-2: Pengukuran kontras warna
        $contrastCheck = $this->checkColorContrast($themeDir);
        $checks['color_contrast'] = $contrastCheck['passed'];
        if (!empty($contrastCheck['errors'])) {
            $errors = array_merge($errors, $contrastCheck['errors']);
        }

        $allPassed = empty($errors);

        if (!$allPassed) {
            $build->update([
                'status' => WpBuild::STATUS_INSPECTION_FAILED,
                'inspection_results' => [
                    'passed' => false,
                    'checks' => $checks,
                    'errors' => $errors,
                    'inspected_at' => now()->toIso8601String(),
                ],
            ]);

            throw new RuntimeException('Meja periksa gagal: ' . implode('; ', $errors));
        }

        // Pengemasan ZIP tema
        $zipFullPath = $this->createThemeZip($build, $themeDir);

        $build->update([
            'zip_path' => $this->workspace->relativeZipPath($build),
            'status' => WpBuild::STATUS_READY,
            'inspection_results' => [
                'passed' => true,
                'checks' => $checks,
                'errors' => [],
                'inspected_at' => now()->toIso8601String(),
                'zip_size_bytes' => is_file($zipFullPath) ? filesize($zipFullPath) : 0,
            ],
        ]);

        return $build;
    }

    /**
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkRequiredFiles(string $themeDir): array
    {
        $required = [
            'style.css',
            'functions.php',
            'header.php',
            'footer.php',
            'front-page.php',
            'index.php',
            'screenshot.png',
        ];

        $errors = [];
        foreach ($required as $file) {
            if (!is_file($themeDir . '/' . $file)) {
                $errors[] = "Berkas wajib tema '{$file}' tidak ditemukan.";
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkStyleHeader(string $themeDir): array
    {
        $stylePath = $themeDir . '/style.css';
        if (!is_file($stylePath)) {
            return ['passed' => false, 'errors' => ["Berkas style.css tidak ditemukan."]];
        }

        $content = (string) file_get_contents($stylePath);
        $errors = [];

        if (!str_contains($content, 'Theme Name:')) {
            $errors[] = "style.css tidak memiliki deklarasi 'Theme Name:'.";
        }
        if (!str_contains($content, 'Author:')) {
            $errors[] = "style.css tidak memiliki deklarasi 'Author:'.";
        }
        if (!str_contains($content, 'Version:')) {
            $errors[] = "style.css tidak memiliki deklarasi 'Version:'.";
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkSyntaxLint(string $themeDir): array
    {
        $phpFiles = glob($themeDir . '/*.php') ?: [];
        $errors = [];

        foreach ($phpFiles as $file) {
            $basename = basename($file);
            $output = [];
            $exitCode = 0;

            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);

            if ($exitCode !== 0) {
                $errors[] = "Kesalahan sintaks PHP pada {$basename}: " . implode(' ', $output);
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkZeroMarkers(string $themeDir): array
    {
        $files = array_merge(
            glob($themeDir . '/*.php') ?: [],
            glob($themeDir . '/*.css') ?: []
        );

        $errors = [];
        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            if (str_contains($content, '__EXITO_')) {
                $errors[] = "Penanda __EXITO_ masih ditemukan pada berkas " . basename($file);
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkMenuRegistration(string $themeDir): array
    {
        $functionsPath = $themeDir . '/functions.php';
        if (!is_file($functionsPath)) {
            return ['passed' => false, 'errors' => ["functions.php tidak ditemukan."]];
        }

        $content = (string) file_get_contents($functionsPath);
        if (!str_contains($content, 'register_nav_menus')) {
            return [
                'passed' => false,
                'errors' => ["functions.php tidak mendaftarkan lokasi menu (register_nav_menus)."],
            ];
        }

        return ['passed' => true, 'errors' => []];
    }

    /**
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkNoExternalEmbeds(string $themeDir): array
    {
        $phpFiles = glob($themeDir . '/*.php') ?: [];
        $errors = [];

        foreach ($phpFiles as $file) {
            $content = (string) file_get_contents($file);
            $basename = basename($file);

            // K-9: Tidak boleh ada iframe atau skrip eksternal asing
            if (preg_match('/<iframe\b/i', $content)) {
                $errors[] = "Ditemukan tag <iframe> yang dilarang pada berkas {$basename}.";
            }
            if (preg_match('/<script\b[^>]*\bsrc\s*=\s*["\']?\s*(?:https?:)?\/\//i', $content)) {
                $errors[] = "Ditemukan skrip eksternal yang dilarang pada berkas {$basename}.";
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkFileSizes(string $themeDir): array
    {
        $errors = [];

        // M-11: style.css <= 60KB
        $stylePath = $themeDir . '/style.css';
        if (is_file($stylePath)) {
            $size = filesize($stylePath);
            if ($size > 60 * 1024) {
                $errors[] = "Ukuran style.css melampaui ambang M-11 (terukur " . round($size / 1024, 1) . " KB, maks 60 KB).";
            }
        }

        // M-11: Tiap berkas templat PHP <= 120KB
        $phpFiles = glob($themeDir . '/*.php') ?: [];
        foreach ($phpFiles as $file) {
            $size = filesize($file);
            if ($size > 120 * 1024) {
                $errors[] = "Ukuran templat " . basename($file) . " melampaui ambang M-11 (terukur " . round($size / 1024, 1) . " KB, maks 120 KB).";
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkColorContrast(string $themeDir): array
    {
        $stylePath = $themeDir . '/style.css';
        if (!is_file($stylePath)) {
            return ['passed' => true, 'errors' => []];
        }

        $css = (string) file_get_contents($stylePath);
        $errors = [];

        // Cari variabel warna di :root { ... }
        if (preg_match('/:root\s*\{([^}]+)\}/is', $css, $rootMatch)) {
            $rootContent = $rootMatch[1];
            preg_match_all('/--([a-zA-Z0-9_-]+)\s*:\s*(#[0-9a-fA-F]{3,6})/i', $rootContent, $varMatches, PREG_SET_ORDER);

            $vars = [];
            foreach ($varMatches as $vm) {
                $vars[$vm[1]] = $vm[2];
            }

            // Jika ada pasangan teks dan background spesifik (misal --text dan --bg atau --brand)
            $textColor = $vars['text-color'] ?? $vars['text'] ?? null;
            $bgColor = $vars['bg-color'] ?? $vars['bg'] ?? $vars['background'] ?? null;

            if ($textColor && $bgColor) {
                $ratio = self::contrastRatio($textColor, $bgColor);
                if ($ratio < 4.5) {
                    $errors[] = "Kontras warna teks ({$textColor}) di atas latar ({$bgColor}) adalah {$ratio}:1, gagal ambang M-1 (min 4.5:1).";
                }
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Mengemas folder tema menjadi satu berkas ZIP siap install di WordPress.
     */
    public function createThemeZip(WpBuild $build, string $themeDir): string
    {
        $zipFullPath = $this->workspace->zipPath($build);
        $themeSlug = Str::slug($build->project->name) ?: 'siteflow-theme';

        if (is_file($zipFullPath)) {
            unlink($zipFullPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat berkas ZIP di: ' . $zipFullPath);
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(realpath($themeDir)) + 1);
            $normalizedPath = str_replace('\\', '/', $relativePath);

            // Letakkan di dalam subfolder theme slug agar WordPress mengekstraknya rapi
            $zip->addFile($filePath, $themeSlug . '/' . $normalizedPath);
        }

        $zip->close();

        if (!is_file($zipFullPath) || filesize($zipFullPath) === 0) {
            throw new RuntimeException('Berkas ZIP tema kosong atau gagal ditulis.');
        }

        return $zipFullPath;
    }

    /**
     * Menghitung rasio kontras WCAG 2.1 antara dua warna heksadesimal.
     */
    public static function contrastRatio(string $hex1, string $hex2): float
    {
        $lum1 = self::relativeLuminance($hex1);
        $lum2 = self::relativeLuminance($hex2);

        $lighter = max($lum1, $lum2);
        $darker = min($lum1, $lum2);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    private static function relativeLuminance(string $hex): float
    {
        $clean = ltrim($hex, '#');
        if (strlen($clean) === 3) {
            $clean = $clean[0] . $clean[0] . $clean[1] . $clean[1] . $clean[2] . $clean[2];
        }

        $r = hexdec(substr($clean, 0, 2)) / 255;
        $g = hexdec(substr($clean, 2, 2)) / 255;
        $b = hexdec(substr($clean, 4, 2)) / 255;

        $rLin = ($r <= 0.04045) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $gLin = ($g <= 0.04045) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $bLin = ($b <= 0.04045) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $rLin + 0.7152 * $gLin + 0.0722 * $bLin;
    }
}
