<?php

namespace App\Services;

use App\Models\WpBuild;
use App\Support\MockupWorkspace;
use FilesystemIterator;
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
    public const NAMED_COLORS = [
        'black' => '#000000',
        'silver' => '#c0c0c0',
        'gray' => '#808080',
        'grey' => '#808080',
        'white' => '#ffffff',
        'maroon' => '#800000',
        'red' => '#ff0000',
        'purple' => '#800080',
        'fuchsia' => '#ff00ff',
        'green' => '#008000',
        'lime' => '#00ff00',
        'olive' => '#808000',
        'yellow' => '#ffff00',
        'navy' => '#000080',
        'blue' => '#0000ff',
        'teal' => '#008080',
        'aqua' => '#00ffff',
        'orange' => '#ffa500',
    ];
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

        // 5b. Bahan importer: halaman isi, menu, pustaka media
        $pagesCheck = $this->checkPagePayloads($themeDir);
        $checks['page_payloads'] = $pagesCheck['passed'];
        if (!empty($pagesCheck['errors'])) {
            $errors = array_merge($errors, $pagesCheck['errors']);
        }

        $menuFillCheck = $this->checkMenuPayload($themeDir);
        $checks['menu_filled'] = $menuFillCheck['passed'];
        if (!empty($menuFillCheck['errors'])) {
            $errors = array_merge($errors, $menuFillCheck['errors']);
        }

        $mediaCheck = $this->checkMediaPayload($themeDir);
        $checks['media_payload'] = $mediaCheck['passed'];
        if (!empty($mediaCheck['errors'])) {
            $errors = array_merge($errors, $mediaCheck['errors']);
        }

        // 5c. Bahan Elementor: satu berkas JSON elemen per halaman
        $elementorCheck = $this->checkElementorPayload($themeDir);
        $checks['elementor_payload'] = $elementorCheck['passed'];
        if (!empty($elementorCheck['errors'])) {
            $errors = array_merge($errors, $elementorCheck['errors']);
        }

        // 5d. Beranda adalah Page statis, bukan front-page.php
        $frontPageCheck = $this->checkFrontPage($themeDir);
        $checks['front_page'] = $frontPageCheck['passed'];
        if (!empty($frontPageCheck['errors'])) {
            $errors = array_merge($errors, $frontPageCheck['errors']);
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
                    'pages_count' => $pagesCheck['count'] ?? 0,
                    'elementor_pages_count' => $elementorCheck['count'] ?? 0,
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
                'pages_count' => $pagesCheck['count'] ?? 0,
                'elementor_pages_count' => $elementorCheck['count'] ?? 0,
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
            'page.php',
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
            glob($themeDir . '/*.css') ?: [],
            glob($themeDir . '/content/*.html') ?: [],
            glob($themeDir . '/content/*.json') ?: []
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
     * Bahan halaman isi: content/halaman.json + content/<slug>.html.
     * Isi yang kosong, token tak dikenal, atau token yang berkas tujuannya
     * tidak ada = build tidak layak dikemas (halaman akan rusak di WordPress).
     *
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkPagePayloads(string $themeDir): array
    {
        $errors = [];
        $meta = $this->contentMeta($themeDir, $errors);

        if ($meta === null) {
            return ['passed' => false, 'errors' => $errors];
        }

        $pages = $meta['halaman'] ?? null;
        $images = $meta['gambar'] ?? null;

        if (!is_array($pages)) {
            $errors[] = 'content/halaman.json: daftar halaman tidak sah.';
            $pages = [];
        }

        if (!is_array($images)) {
            $errors[] = 'content/halaman.json: daftar gambar tidak sah.';
            $images = [];
        }

        $pageSlugs = [];

        foreach ($pages as $page) {
            if (!is_array($page) || !isset($page['slug']) || preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) $page['slug']) !== 1) {
                $errors[] = 'content/halaman.json: ada halaman tanpa slug yang sah.';
                continue;
            }

            $slug = (string) $page['slug'];

            if (in_array($slug, $pageSlugs, true)) {
                $errors[] = "Halaman isi '{$slug}' tercatat lebih dari sekali.";
                continue;
            }

            $pageSlugs[] = $slug;
        }

        foreach ($pages as $page) {
            if (!is_array($page) || !isset($page['slug']) || preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) $page['slug']) !== 1) {
                continue;
            }

            $slug = (string) $page['slug'];

            if (trim((string) ($page['judul'] ?? '')) === '') {
                $errors[] = "Halaman isi '{$slug}' tidak punya judul.";
            }

            $contentPath = $themeDir . '/content/' . $slug . '.html';

            if (!is_file($contentPath)) {
                $errors[] = "Berkas content/{$slug}.html tidak ditemukan.";
                continue;
            }

            $content = (string) file_get_contents($contentPath);

            if (trim(strip_tags($content)) === '') {
                $errors[] = "Isi halaman '{$slug}' kosong.";
                continue;
            }

            if (str_contains($content, '__EXITO_')) {
                $errors[] = "Penanda __EXITO_ masih ditemukan pada content/{$slug}.html.";
            }

            $errors = array_merge($errors, $this->contentTokenErrors($content, $slug, $themeDir, $images, $pageSlugs));
        }

        // Tautan halaman di templat tema harus menunjuk halaman yang benar-benar
        // dibuat; kalau tidak, tautan di situs mati (K-7).
        foreach (glob($themeDir . '/*.php') ?: [] as $templateFile) {
            preg_match_all('/siteflow_page_url\(\s*\'([a-z0-9-]+)\'\s*\)/', (string) file_get_contents($templateFile), $templateRefs);

            foreach (array_unique($templateRefs[1]) as $referencedSlug) {
                if (!in_array($referencedSlug, $pageSlugs, true)) {
                    $errors[] = 'Templat ' . basename($templateFile) . " menaut ke halaman '{$referencedSlug}' yang tidak dibuat.";
                }
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'count' => count($pageSlugs),
        ];
    }

    /**
     * @param list<string> $images
     * @param list<string> $pageSlugs
     * @return list<string>
     */
    private function contentTokenErrors(string $content, string $slug, string $themeDir, array $images, array $pageSlugs): array
    {
        preg_match_all('/\[\[siteflow:([^\]]*)\]\]/', $content, $tokens, PREG_SET_ORDER);
        $errors = [];

        foreach ($tokens as $token) {
            $parts = explode(':', $token[1]);
            $kind = $parts[0] ?? '';
            $value = $parts[1] ?? '';

            if ($kind === 'gambar' && $value !== '') {
                if (!in_array($value, $images, true)) {
                    $errors[] = "Halaman '{$slug}' memakai gambar '{$value}' yang tidak terdaftar di daftar gambar tema.";
                } elseif (!is_file($themeDir . '/images/' . $value)) {
                    $errors[] = "Halaman '{$slug}' memakai gambar '{$value}' yang tidak ada di folder images/.";
                }

                continue;
            }

            if ($kind === 'halaman' && $value !== '') {
                if (!in_array($value, $pageSlugs, true)) {
                    $errors[] = "Halaman '{$slug}' menaut ke halaman '{$value}' yang tidak dibuat.";
                }

                continue;
            }

            $errors[] = "Halaman '{$slug}' memuat token yang tidak dikenal: {$token[0]}.";
        }

        return $errors;
    }

    /**
     * Bahan menu: fungsi importer ada di functions.php, dan setiap item menu
     * menunjuk halaman isi yang benar-benar dibuat.
     *
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkMenuPayload(string $themeDir): array
    {
        $errors = [];
        $functionsPath = $themeDir . '/functions.php';

        if (!is_file($functionsPath)) {
            return ['passed' => false, 'errors' => ['functions.php tidak ditemukan.']];
        }

        $functions = (string) file_get_contents($functionsPath);

        foreach (['siteflow_import_pages', 'siteflow_import_media', 'siteflow_import_menu', 'siteflow_page_url'] as $requiredFunction) {
            if (!str_contains($functions, 'function ' . $requiredFunction . '(')) {
                $errors[] = "functions.php tidak memuat {$requiredFunction}(), jadi menu tidak akan dibuat.";
            }
        }

        $meta = $this->contentMeta($themeDir, $errors);

        if ($meta === null) {
            return ['passed' => false, 'errors' => $errors];
        }

        $pages = is_array($meta['halaman'] ?? null) ? $meta['halaman'] : [];
        $nav = is_array($meta['nav'] ?? null) ? $meta['nav'] : [];
        $pageSlugs = array_values(array_filter(array_map(
            fn ($page) => is_array($page) ? (string) ($page['slug'] ?? '') : '',
            $pages
        )));

        if (array_diff($pageSlugs, ['beranda']) !== [] && $nav === []) {
            $errors[] = 'Tema punya halaman isi tetapi tidak punya item menu; "Menu Utama" akan kosong.';
        }

        $seen = [];

        foreach ($nav as $item) {
            if (!is_array($item) || !isset($item['slug'])) {
                $errors[] = 'content/halaman.json: ada item menu tanpa slug.';
                continue;
            }

            $slug = (string) $item['slug'];

            if (!in_array($slug, $pageSlugs, true)) {
                $errors[] = "Item menu '{$slug}' tidak punya halaman isi.";
            }

            if (isset($seen[$slug])) {
                $errors[] = "Item menu '{$slug}' tercatat lebih dari sekali.";
            }

            if (trim((string) ($item['label'] ?? '')) === '') {
                $errors[] = "Item menu '{$slug}' tidak punya label.";
            }

            $seen[$slug] = true;
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Bahan pustaka media: setiap berkas images/ terdaftar dan benar-benar
     * gambar, dan setiap berkas terdaftar ada di images/. SVG sengaja tidak
     * diterima: WordPress menolaknya secara bawaan dan SVG bisa membawa skrip.
     *
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkMediaPayload(string $themeDir): array
    {
        $errors = [];
        $meta = $this->contentMeta($themeDir, $errors);

        if ($meta === null) {
            return ['passed' => false, 'errors' => $errors];
        }

        $listed = is_array($meta['gambar'] ?? null) ? array_map('strval', $meta['gambar']) : [];
        $files = array_map('basename', glob($themeDir . '/images/*') ?: []);
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        foreach ($listed as $filename) {
            if (!is_file($themeDir . '/images/' . $filename)) {
                $errors[] = "Gambar '{$filename}' tercatat di content/halaman.json tetapi tidak ada di images/.";
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowed, true)) {
                $errors[] = "Gambar '{$filename}' berformat .{$extension}; yang diterima hanya " . implode(', ', $allowed) . '.';
                continue;
            }

            if (getimagesize($themeDir . '/images/' . $filename) === false) {
                $errors[] = "Berkas images/{$filename} bukan gambar yang sah.";
            }
        }

        foreach ($files as $filename) {
            if (!in_array($filename, $listed, true)) {
                $errors[] = "Berkas images/{$filename} tidak terdaftar di content/halaman.json, jadi tidak akan masuk pustaka media.";
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Bahan Elementor: setiap halaman punya content/<slug>.json berisi elemen
     * visual yang bisa disunting. Diperiksa keberadaan berkas, struktur elemen,
     * jenis widget, dan token gambar/halaman di dalam nilainya.
     *
     * @return array{passed: bool, errors: list<string>, count: int}
     */
    public function checkElementorPayload(string $themeDir): array
    {
        $errors = [];
        $meta = $this->contentMeta($themeDir, $errors);

        if ($meta === null) {
            return ['passed' => false, 'errors' => $errors, 'count' => 0];
        }

        $pages = is_array($meta['halaman'] ?? null) ? $meta['halaman'] : [];
        $images = is_array($meta['gambar'] ?? null) ? array_map('strval', $meta['gambar']) : [];
        $pageSlugs = array_values(array_filter(array_map(
            fn ($page) => is_array($page) ? (string) ($page['slug'] ?? '') : '',
            $pages
        )));

        foreach ($pages as $page) {
            if (!is_array($page) || !isset($page['slug']) || preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) $page['slug']) !== 1) {
                continue;
            }

            $slug = (string) $page['slug'];
            $path = $themeDir . '/content/' . $slug . '.json';

            if (!is_file($path)) {
                $errors[] = "Berkas content/{$slug}.json tidak ditemukan; halaman tidak membawa elemen Elementor.";
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            if (!is_array($decoded)) {
                $errors[] = "Berkas content/{$slug}.json tidak bisa dibaca sebagai JSON.";
                continue;
            }

            if (trim((string) ($decoded['versi'] ?? '')) === '') {
                $errors[] = "Berkas content/{$slug}.json tidak memuat versi isi.";
            }

            $elements = $decoded['elementor'] ?? null;

            if (!is_array($elements) || $elements === []) {
                $errors[] = "Berkas content/{$slug}.json tidak memuat elemen Elementor.";
                continue;
            }

            $errors = array_merge($errors, $this->elementorElementErrors($elements, $slug, $images, $pageSlugs));
        }

        foreach (glob($themeDir . '/content/*.json') ?: [] as $file) {
            $slug = basename($file, '.json');

            if ($slug !== 'halaman' && !in_array($slug, $pageSlugs, true)) {
                $errors[] = "Berkas content/{$slug}.json tidak punya halaman di content/halaman.json.";
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'count' => count($pageSlugs),
        ];
    }

    /**
     * @param list<mixed> $elements
     * @param list<string> $images
     * @param list<string> $pageSlugs
     * @return list<string>
     */
    private function elementorElementErrors(array $elements, string $slug, array $images, array $pageSlugs, int $depth = 0): array
    {
        if ($depth > 12) {
            return ["Halaman '{$slug}': susunan elemen terlalu dalam."];
        }

        $errors = [];
        $widgets = ['heading', 'text-editor', 'image', 'button'];

        foreach ($elements as $element) {
            if (!is_array($element)) {
                $errors[] = "Halaman '{$slug}': ada elemen Elementor tanpa bentuk objek.";
                continue;
            }

            $id = isset($element['id']) && is_string($element['id']) ? trim($element['id']) : '';

            if ($id === '') {
                $errors[] = "Halaman '{$slug}': ada elemen tanpa id.";
            }

            $type = isset($element['elType']) && is_string($element['elType']) ? $element['elType'] : '';

            if ($type === 'container') {
                $children = $element['elements'] ?? [];

                if (!is_array($children)) {
                    $errors[] = "Halaman '{$slug}': kontainer '{$id}' tanpa daftar anak yang sah.";
                    continue;
                }

                $errors = array_merge($errors, $this->elementorElementErrors($children, $slug, $images, $pageSlugs, $depth + 1));
                continue;
            }

            if ($type !== 'widget') {
                $errors[] = "Halaman '{$slug}': jenis elemen '{$type}' tidak dikenal.";
                continue;
            }

            $widget = isset($element['widgetType']) && is_string($element['widgetType']) ? $element['widgetType'] : '';

            $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];

            if ($widget === 'html') {
                $markup = isset($settings['html']) && is_string($settings['html']) ? $settings['html'] : '';

                if ($markup === '' || ElementorSvgMarkup::sanitize($markup) === null) {
                    $errors[] = "Halaman '{$slug}': widget '{$widget}' tidak dipakai; hanya SVG inline yang sudah disaring yang boleh memakainya.";
                    continue;
                }
            } elseif (!in_array($widget, $widgets, true)) {
                $errors[] = "Halaman '{$slug}': widget '{$widget}' tidak dipakai; isi harus elemen visual yang bisa disunting.";
                continue;
            }

            if ($widget === 'image') {
                $url = isset($settings['image']['url']) && is_string($settings['image']['url'])
                    ? trim($settings['image']['url'])
                    : '';

                if ($url === '') {
                    $errors[] = "Halaman '{$slug}': widget gambar tanpa sumber gambar.";
                }
            }

            if ($widget === 'heading' && trim((string) ($settings['title'] ?? '')) === '') {
                $errors[] = "Halaman '{$slug}': ada judul kosong.";
            }

            if ($widget === 'button') {
                $text = trim((string) ($settings['text'] ?? ''));
                $url = isset($settings['link']['url']) ? trim((string) $settings['link']['url']) : '';

                if ($text === '' || $url === '') {
                    $errors[] = "Halaman '{$slug}': ada tombol tanpa teks atau tautan.";
                }
            }

            $serialized = (string) json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (str_contains($serialized, '__EXITO_')) {
                $errors[] = "Halaman '{$slug}': penanda __EXITO_ masih ada di data Elementor.";
            }

            preg_match_all('/\[\[siteflow:([a-z]+):([^\]]+)\]\]/', $serialized, $tokens, PREG_SET_ORDER);

            foreach ($tokens as $token) {
                if ($token[1] === 'gambar') {
                    if (!in_array($token[2], $images, true)) {
                        $errors[] = "Halaman '{$slug}': gambar '{$token[2]}' di data Elementor tidak terdaftar.";
                    }
                } elseif ($token[1] === 'halaman') {
                    if (!in_array($token[2], $pageSlugs, true)) {
                        $errors[] = "Halaman '{$slug}': data Elementor menaut ke halaman '{$token[2]}' yang tidak dibuat.";
                    }
                } else {
                    $errors[] = "Halaman '{$slug}': data Elementor memuat token tidak dikenal: {$token[0]}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Beranda adalah Page WordPress sungguhan. Importer memasangnya sebagai
     * halaman depan statis; front-page.php tidak boleh ada karena menimpanya.
     *
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkFrontPage(string $themeDir): array
    {
        $errors = [];
        $meta = $this->contentMeta($themeDir, $errors);

        if ($meta === null) {
            return ['passed' => false, 'errors' => $errors];
        }

        $slugs = array_values(array_filter(array_map(
            fn ($page) => is_array($page) ? (string) ($page['slug'] ?? '') : '',
            is_array($meta['halaman'] ?? null) ? $meta['halaman'] : []
        )));

        if (!in_array('beranda', $slugs, true)) {
            $errors[] = 'content/halaman.json tidak memuat halaman beranda; halaman depan tidak akan dibuat.';
        }

        $functionsPath = $themeDir . '/functions.php';
        $functions = is_file($functionsPath) ? (string) file_get_contents($functionsPath) : '';

        foreach (['show_on_front', 'page_on_front'] as $option) {
            if (!str_contains($functions, "'" . $option . "'")) {
                $errors[] = "functions.php tidak memasang halaman depan statis ({$option}).";
            }
        }

        if (is_file($themeDir . '/front-page.php')) {
            $errors[] = 'front-page.php masih ada; templat tema itu menimpa beranda Elementor.';
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param list<string> $errors
     * @return array<string, mixed>|null
     */
    private function contentMeta(string $themeDir, array &$errors): ?array
    {
        $path = $themeDir . '/content/halaman.json';

        if (!is_file($path)) {
            $errors[] = 'Berkas content/halaman.json tidak ditemukan; tema tidak membawa bahan importer.';

            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            $errors[] = 'content/halaman.json tidak bisa dibaca sebagai JSON.';

            return null;
        }

        if (trim((string) ($decoded['versi'] ?? '')) === '') {
            $errors[] = 'content/halaman.json tidak memuat versi isi.';
        }

        if (trim((string) ($decoded['kunci'] ?? '')) === '') {
            $errors[] = 'content/halaman.json tidak memuat kunci tema.';
        }

        return $decoded;
    }

    /**
     * K-9: tanpa skrip, formulir, iframe, dan alamat jaringan. Pemeriksaan
     * membaca CSS juga, bukan hanya templat PHP - @import atau url() dari
     * luar di style.css lolos dari pemindaian lama.
     *
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkNoExternalEmbeds(string $themeDir): array
    {
        $errors = [];
        $allowedHosts = ['wa.me', 'www.w3.org', 'w3.org'];

        foreach ($this->themeTextFiles($themeDir, ['php', 'css', 'html', 'json']) as $file) {
            $content = $this->stripComments((string) file_get_contents($file));
            $basename = basename($file);

            if (preg_match('/<iframe\b/i', $content)) {
                $errors[] = "Ditemukan tag <iframe> yang dilarang pada berkas {$basename}.";
            }

            if (preg_match('/<script\b/i', $content)) {
                $errors[] = "Ditemukan tag <script> yang dilarang pada berkas {$basename}.";
            }

            if (preg_match('/<form\b/i', $content)) {
                $errors[] = "Ditemukan formulir yang dilarang pada berkas {$basename}.";
            }

            if (preg_match('/<(?:object|embed|applet)\b/i', $content)) {
                $errors[] = "Ditemukan tag <object>/<embed> yang dilarang pada berkas {$basename}.";
            }

            if (preg_match('/\son[a-z]+\s*=/i', $content)) {
                $errors[] = "Ditemukan penanganan peristiwa inline (on...=) yang dilarang pada berkas {$basename}.";
            }

            if (preg_match('/javascript\s*:/i', $content)) {
                $errors[] = "Ditemukan tautan javascript: yang dilarang pada berkas {$basename}.";
            }

            preg_match_all('~(?:(?:https?:)?//)([a-z0-9.\-]+\.[a-z]{2,})~i', $content, $hosts);

            foreach (array_unique(array_map('strtolower', $hosts[1])) as $host) {
                if (!in_array($host, $allowedHosts, true)) {
                    $errors[] = "Alamat luar '{$host}' dilarang pada berkas {$basename}.";
                }
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
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

        // M-11: Tiap berkas isi halaman <= 120KB
        foreach (glob($themeDir . '/content/*.html') ?: [] as $file) {
            $size = filesize($file);
            if ($size > 120 * 1024) {
                $errors[] = "Ukuran isi halaman content/" . basename($file) . " melampaui ambang M-11 (terukur " . round($size / 1024, 1) . " KB, maks 120 KB).";
            }
        }

        // M-11: Tiap muatan Elementor <= 120KB
        foreach (glob($themeDir . '/content/*.json') ?: [] as $file) {
            $size = filesize($file);
            if ($size > 120 * 1024) {
                $errors[] = "Ukuran muatan Elementor content/" . basename($file) . " melampaui ambang M-11 (terukur " . round($size / 1024, 1) . " KB, maks 120 KB).";
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * M-1 dan M-2: ukur pasangan teks dan latar yang benar-benar dipakai di
     * CSS, bukan mencari nama variabel tertentu. Nama variabel bebas; yang
     * dibaca adalah nilai yang sudah diurai (var() dicari di :root).
     *
     * @return array{passed: bool, errors: list<string>}
     */
    public function checkColorContrast(string $themeDir): array
    {
        $stylePath = $themeDir . '/style.css';
        if (!is_file($stylePath)) {
            return ['passed' => true, 'errors' => []];
        }

        $css = $this->stripComments((string) file_get_contents($stylePath));
        $vars = $this->hexVariables($css);
        $errors = [];
        $measured = 0;
        $gagal = [];

        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as $rule) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
            $body = $rule[2];

            // Salinan jembatan Elementor hanya mengulang aturan mockup dengan
            // lingkup tambahan. Diukur dua kali berarti satu pasangan warna
            // dilaporkan berkali-kali.
            if (str_contains($selector, '.siteflow-page') || str_contains($selector, '.elementor-edit-area')) {
                continue;
            }

            $color = $this->declaredColor($body, $vars);
            $background = $this->declaredBackground($body, $vars);

            if ($color === null || $background === null) {
                continue;
            }

            $measured++;
            $threshold = $this->isLargeText($body) ? 3.0 : 4.5;
            $ratio = self::contrastRatio($color['hex'], $background['hex']);

            if ($ratio < $threshold) {
                $kunci = $color['hex'] . '/' . $background['hex'] . '/' . $threshold;
                $gagal[$kunci]['aturan'] = ($gagal[$kunci]['aturan'] ?? 0) + 1;
                $gagal[$kunci]['pemilih'] ??= $selector;
                $gagal[$kunci]['rasio'] = round($ratio, 2);
                continue;
            }

            $overBrand = $background['var'] !== null
                && preg_match('/brand|accent|primary|cta|aksi|merek/i', $background['var']) === 1;

            if (!$overBrand) {
                continue;
            }

            $best = 0.0;
            foreach (array_merge(array_values($vars), ['#ffffff', '#000000']) as $candidate) {
                $best = max($best, self::contrastRatio($candidate, $background['hex']));
            }

            if ($ratio < $best - 0.01) {
                $errors[] = "M-2 pada '{$selector}': teks {$color['hex']} di atas warna merek {$background['hex']} berasio {$ratio}:1, padahal ada kandidat berasio {$best}:1.";
            }
        }

        foreach ($gagal as $kunci => $satu) {
            [$hexTeks, $hexLatar, $ambang] = explode('/', $kunci);
            $lain = $satu['aturan'] > 1 ? " (muncul di {$satu['aturan']} aturan)" : '';

            $errors[] = "Kontras teks {$hexTeks} di atas latar {$hexLatar} pada '{$satu['pemilih']}'{$lain} adalah {$satu['rasio']}:1, di bawah ambang M-1 ({$ambang}:1).";
        }

        if ($measured === 0) {
            $errors[] = 'Tidak ada pasangan warna teks dan latar yang bisa diukur di style.css (M-1). Tulis warna teks dan latar pada aturan yang sama.';
        }

        return [
            'passed' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function hexVariables(string $css): array
    {
        $vars = [];

        preg_match_all('/--([a-zA-Z0-9_-]+)\s*:\s*(#[0-9a-fA-F]{8}|#[0-9a-fA-F]{6}|#[0-9a-fA-F]{3})\b/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $hex = $this->opaqueHex($match[2]);

            if ($hex !== null) {
                $vars[$match[1]] = $hex;
            }
        }

        return $vars;
    }

    /**
     * @param array<string, string> $vars
     * @return array{hex: string, var: string|null}|null
     */
    private function declaredColor(string $body, array $vars): ?array
    {
        if (!preg_match('/(?<![-\w])color\s*:\s*([^;]+)/i', $body, $match)) {
            return null;
        }

        return $this->resolveColor($match[1], $vars);
    }

    /**
     * @param array<string, string> $vars
     * @return array{hex: string, var: string|null}|null
     */
    private function declaredBackground(string $body, array $vars): ?array
    {
        if (!preg_match('/(?<![-\w])background(?:-color)?\s*:\s*([^;]+)/i', $body, $match)) {
            return null;
        }

        if (stripos($match[1], 'gradient(') !== false || stripos($match[1], 'url(') !== false) {
            return null;
        }

        return $this->resolveColor($match[1], $vars);
    }

    /**
     * @param array<string, string> $vars
     * @return array{hex: string, var: string|null}|null
     */
    private function resolveColor(string $value, array $vars): ?array
    {
        $value = trim($value);

        if (preg_match('/^var\(\s*--([a-zA-Z0-9_-]+)\s*(?:,\s*([^)]*))?\)\s*(?:!\s*important)?$/i', $value, $match)) {
            $name = $match[1];

            if (isset($vars[$name])) {
                return ['hex' => $vars[$name], 'var' => $name];
            }

            $value = trim($match[2] ?? '');
        }

        if (preg_match('/(#[0-9a-fA-F]{8}|#[0-9a-fA-F]{6}|#[0-9a-fA-F]{3})\b/', $value, $match)) {
            $hex = $this->opaqueHex($match[1]);

            return $hex === null ? null : ['hex' => $hex, 'var' => null];
        }

        $named = self::NAMED_COLORS[strtolower($value)] ?? null;

        if ($named !== null) {
            return ['hex' => $named, 'var' => null];
        }

        if (preg_match('/rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([\d.]+))?\s*\)/i', $value, $match)) {
            $alpha = isset($match[4]) && $match[4] !== '' ? (float) $match[4] : 1.0;

            if ($alpha < 1.0) {
                return null;
            }

            return [
                'hex' => sprintf('#%02x%02x%02x', min(255, (int) $match[1]), min(255, (int) $match[2]), min(255, (int) $match[3])),
                'var' => null,
            ];
        }

        return null;
    }

    /**
     * Warna semi-transparan tidak punya satu nilai datar yang bisa diukur;
     * kembalikan null agar pasangan itu dilewati, bukan diukur salah.
     */
    private function opaqueHex(string $hex): ?string
    {
        if (strlen($hex) === 9) {
            if (strtolower(substr($hex, 7)) !== 'ff') {
                return null;
            }

            return substr($hex, 0, 7);
        }

        return $hex;
    }

    private function isLargeText(string $body): bool
    {
        if (!preg_match('/font-size\s*:\s*([\d.]+)\s*(px|rem|em)/i', $body, $match)) {
            return false;
        }

        $size = (float) $match[1];
        $px = strtolower($match[2]) === 'px' ? $size : $size * 16;

        if ($px >= 24) {
            return true;
        }

        return $px >= 18.66 && preg_match('/font-weight\s*:\s*(?:bold|[6-9]00)/i', $body) === 1;
    }

    private function stripComments(string $content): string
    {
        return (string) preg_replace('~/\*.*?\*/~s', '', $content);
    }

    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    private function themeTextFiles(string $themeDir, array $extensions): array
    {
        if (!is_dir($themeDir)) {
            return [];
        }

        $files = [];
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if ($item->isFile() && in_array(strtolower($item->getExtension()), $extensions, true)) {
                $files[] = $item->getPathname();
            }
        }

        return $files;
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
