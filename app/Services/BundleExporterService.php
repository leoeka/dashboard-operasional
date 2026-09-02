<?php

namespace App\Services;

use ZipArchive;

class BundleExporterService
{
    public function export(array $bundle, string $outputDir): string
    {
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new \RuntimeException('Folder export bundle tidak dapat dibuat.');
        }

        $themeInstallPath = $outputDir . DIRECTORY_SEPARATOR . 'theme-install.zip';
        $this->createThemeInstallArchive($bundle, $themeInstallPath);

        $zipPath = $outputDir . DIRECTORY_SEPARATOR . 'bundle-export.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('File ZIP bundle tidak dapat dibuat.');
        }

        $wordpressFiles = $bundle['wordpress']['files'] ?? [];
        $themeRoot = trim((string) ($bundle['theme']['name'] ?? 'exito-client-theme'), '/');
        $pluginRoot = trim((string) ($bundle['plugin']['name'] ?? 'exito-core'), '/');
        $themeFiles = [];
        $pluginFiles = [];
        $elementorFiles = [];

        foreach ($wordpressFiles as $relativePath => $contents) {
            if (!is_string($relativePath) || !is_string($contents)) {
                continue;
            }

            $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
            if (str_starts_with($relativePath, $themeRoot . '/')) {
                $themeFiles[$relativePath] = $contents;
            } elseif (str_starts_with($relativePath, $pluginRoot . '/')) {
                $pluginFiles[$relativePath] = $contents;
            } elseif (str_starts_with($relativePath, 'elementor/')) {
                $elementorFiles[substr($relativePath, strlen('elementor/'))] = $contents;
            }
        }

        // Deterministic (non-AI) page importer, appended to the plugin so
        // every generated bundle is actually editable in WordPress's
        // built-in Block Editor with no extra plugin — see
        // ElementorPageBuilderService for why this isn't left to the AI
        // builder to hand-write itself.
        $this->injectPageImporter($bundle, $pluginFiles, $pluginRoot);

        // The client's real logo/photos (BundleBuilderService::collectAssets),
        // written into the theme at the exact same fixed paths the AI
        // builder prompts were told to reference — guarantees the real
        // files exist regardless of what the AI actually did with them.
        $this->embedThemeAssets($bundle, $themeFiles, $themeRoot);
        $this->bustThemeStyleVersion($themeFiles, $themeRoot);

        $temporaryArchives = [];
        if ($themeFiles) {
            $temporaryArchives[] = $this->addComponentArchive($zip, '01-theme/' . $themeRoot . '.zip', $themeFiles);
        }
        if ($pluginFiles) {
            $temporaryArchives[] = $this->addComponentArchive($zip, '02-plugin/' . $pluginRoot . '.zip', $pluginFiles);
        }

        foreach ($elementorFiles as $relativePath => $contents) {
            $zip->addFromString('03-elementor/' . $relativePath, $contents);
        }

        $files = [
            'content' => $bundle['content'] ?? [],
            'assets' => $bundle['assets'] ?? [],
        ];

        foreach ($files as $folder => $payload) {
            $targetFolder = $folder === 'assets' ? '05-assets' : '04-content';
            $zip->addFromString($targetFolder . '/README.txt', "Bundle component: {$folder}\n");
            if (is_array($payload)) {
                foreach ($payload as $key => $value) {
                    if (is_array($value)) {
                        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $zip->addFromString($targetFolder . '/' . $key . '.json', $json);
                    } else {
                        $zip->addFromString($targetFolder . '/' . $key . '.txt', (string) $value);
                    }
                }
            }
        }

        $zip->addFromString('05-assets/README.txt', "Copy client assets to the WordPress media library during setup.\n");
        $zip->addFromString('README.md', $this->buildReadme($themeRoot, $pluginRoot));
        $zip->close();

        foreach ($temporaryArchives as $temporaryArchive) {
            @unlink($temporaryArchive);
        }

        // bundle-export.zip is the real deliverable (theme + plugin +
        // Elementor content + assets + README). theme-install.zip is kept
        // alongside it only as a quick theme-only convenience copy.
        return $zipPath;
    }

    private function createThemeInstallArchive(array $bundle, string $archivePath): void
    {
        $wordpressFiles = $bundle['wordpress']['files'] ?? [];
        $themeRoot = trim((string) ($bundle['theme']['name'] ?? 'exito-client-theme'), '/');
        $themeFiles = [];

        foreach ($wordpressFiles as $relativePath => $contents) {
            if (is_string($relativePath) && is_string($contents)) {
                $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
                if (str_starts_with($relativePath, $themeRoot . '/')) {
                    $themeFiles[$relativePath] = $contents;
                }
            }
        }

        if (empty($themeFiles)) {
            throw new \RuntimeException('File theme WordPress tidak ditemukan dalam hasil build.');
        }

        $functionsPath = $themeRoot . '/functions.php';
        $themeFiles[$functionsPath] = ($themeFiles[$functionsPath] ?? "<?php\n") . $this->themeSetupCode($bundle);
        $themeFiles[$themeRoot . '/screenshot.png'] = $this->themeScreenshot($bundle);
        $this->embedThemeAssets($bundle, $themeFiles, $themeRoot);
        $this->bustThemeStyleVersion($themeFiles, $themeRoot);

        $themeZip = new ZipArchive();
        if ($themeZip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP theme WordPress tidak dapat dibuat.');
        }

        foreach ($themeFiles as $relativePath => $contents) {
            $themeZip->addFromString($relativePath, $contents);
        }

        $themeZip->close();
    }

    private function themeSetupCode(array $bundle): string
    {
        $content = $bundle['content'] ?? [];
        $brand = $bundle['brand'] ?? [];
        $homeTitle = (string) data_get($content, 'hero.title', $brand['company_name'] ?? 'Welcome');
        $homeDescription = (string) data_get($content, 'hero.subtitle', data_get($content, 'about.content', ''));
        $homeTitleCode = var_export($homeTitle, true);
        $homeDescriptionCode = var_export($homeDescription, true);

        $setup = <<<'WORDPRESS_SETUP'

add_action('after_switch_theme', function () {
    $page_id = get_page_by_path('home');
    if (!$page_id) {
        $page_id = wp_insert_post([
            'post_title' => __HOME_TITLE__,
            'post_name' => 'home',
            'post_content' => '<p>' . esc_html(__HOME_DESCRIPTION__) . '</p>',
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);
    }
    if ($page_id && !is_wp_error($page_id)) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $page_id);
    }
});
WORDPRESS_SETUP;

        return str_replace(
            ['__HOME_TITLE__', '__HOME_DESCRIPTION__'],
            [$homeTitleCode, $homeDescriptionCode],
            $setup
        );
    }

    private function themeScreenshot(array $bundle): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }

        $brand = $bundle['brand'] ?? [];
        $content = $bundle['content'] ?? [];
        $title = (string) data_get($content, 'hero.title', $brand['company_name'] ?? 'Client Website');
        $primary = (string) ($brand['primary_color'] ?? '#1F2937');
        $primary = ltrim($primary, '#');
        if (strlen($primary) !== 6 || !ctype_xdigit($primary)) {
            $primary = '1F2937';
        }

        $image = imagecreatetruecolor(1200, 900);
        $background = imagecolorallocate($image, 248, 250, 252);
        $accent = imagecolorallocate($image, hexdec(substr($primary, 0, 2)), hexdec(substr($primary, 2, 2)), hexdec(substr($primary, 4, 2)));
        $ink = imagecolorallocate($image, 15, 23, 42);
        $muted = imagecolorallocate($image, 100, 116, 139);
        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 0, 0, 1200, 92, $accent);
        imagefilledrectangle($image, 0, 92, 1200, 500, $accent);
        imagefilledrectangle($image, 56, 150, 1144, 448, $background);
        imagestring($image, 5, 86, 182, substr($title, 0, 48), $ink);
        imagestring($image, 3, 86, 238, 'AI-generated WordPress website', $muted);
        imagefilledrectangle($image, 86, 292, 270, 336, $accent);
        imagestring($image, 3, 110, 302, 'Explore website', $background);
        imagefilledrectangle($image, 56, 550, 360, 760, $background);
        imagefilledrectangle($image, 420, 550, 724, 760, $background);
        imagefilledrectangle($image, 784, 550, 1144, 760, $background);
        imagestring($image, 4, 86, 810, 'Client theme preview', $ink);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return (string) $png;
    }

    /**
     * Step-by-step install guide in Bahasa Indonesia, written for someone
     * installing this for the first time — exact WP Admin menu paths and
     * the exact filename to pick at each upload step (using the theme/
     * plugin's real folder names, so it matches what's actually in the ZIP
     * rather than a generic placeholder).
     */
    private function buildReadme(string $themeRoot, string $pluginRoot): string
    {
        $themeZip = $themeRoot . '.zip';
        $pluginZip = $pluginRoot . '.zip';

        return <<<MD
# Cara Install Website WordPress Ini

File ini kamu dapat sebagai satu ZIP besar (`bundle-export.zip`). Di dalamnya
ada beberapa folder — yang benar-benar kamu upload ke WordPress cuma 2 file
ZIP kecil di dalam folder `01-theme/` dan `02-plugin/`, BUKAN ZIP besar ini.

```
bundle-export.zip           <- ZIP besar yang kamu download, extract dulu
├─ 01-theme/
│  └─ {$themeZip}       <- upload ini di langkah 2 (Theme)
├─ 02-plugin/
│  └─ {$pluginZip}             <- upload ini di langkah 3 (Plugin)
├─ 04-content/                <- referensi teks isi web (tidak perlu diupload)
├─ 05-assets/                 <- referensi aset client (tidak perlu diupload)
└─ README.md                  <- file ini
```

## Langkah 1 — Extract dulu
Klik kanan `bundle-export.zip` (atau file `project-...-wordpress-bundle.zip`
yang kamu download dari dashboard) → **Extract Here / Extract All**. Jangan
upload file ZIP besar ini langsung ke WordPress, dia bukan theme atau plugin
— cuma folder pembungkus.

## Langkah 2 — Pasang Theme
1. Login ke **WordPress Admin** (`namadomain.com/wp-admin`).
2. Buka menu **Appearance > Themes**.
3. Klik **Add New Theme** (di bagian atas halaman) → **Upload Theme**.
4. Klik **Choose File**, cari folder hasil extract tadi, masuk ke folder
   `01-theme/`, pilih file **`{$themeZip}`**.
5. Klik **Install Now**, tunggu sampai selesai, lalu klik **Activate**.

## Langkah 3 — Pasang Plugin
1. Masih di WordPress Admin, buka menu **Plugins > Installed Plugins**.
2. Klik **Add New Plugin** (di bagian atas halaman) → **Upload Plugin**.
3. Klik **Choose File**, masuk ke folder `02-plugin/`, pilih file
   **`{$pluginZip}`**.
4. Klik **Install Now**, tunggu sampai selesai, lalu klik **Activate**.

**Penting:** Theme harus lebih dulu terpasang & aktif SEBELUM plugin
diaktifkan — urutannya theme dulu (Langkah 2), baru plugin (Langkah 3).

## Langkah 4 — Selesai, halaman sudah otomatis jadi
Begitu plugin aktif, semua halaman (Home, About, Services, Contact, dst)
otomatis dibuat lengkap dengan isi teks & foto, dan Home otomatis dijadikan
halaman depan website. Tidak ada langkah import manual, tidak perlu plugin
tambahan apa pun (termasuk tidak perlu Elementor).

Untuk memastikan:
- Buka menu **Pages** di WordPress Admin — harus muncul halaman Home,
  About, Services, Contact (atau sesuai isi proposal), semuanya berstatus
  **Published**.
- Buka **Settings > Reading** — pastikan "Your homepage displays" sudah
  otomatis terset ke **A static page**, dengan Homepage = **Home**.
- Kunjungi alamat website-nya langsung untuk lihat hasilnya.

Kalau setelah plugin diaktifkan halaman-halaman itu belum muncul, buka
sembarang halaman lain di WordPress Admin (misalnya klik menu Dashboard)
lalu cek lagi menu Pages — proses pembuatan halaman jalan otomatis begitu
ada admin yang membuka wp-admin setelah plugin aktif.

## Langkah 5 — Edit isi halaman
Untuk mengubah teks, foto, atau urutan section:
1. Buka menu **Pages**, klik halaman yang mau diedit (misalnya "Home").
2. Klik **Edit** — akan terbuka **Block Editor** bawaan WordPress.
3. Setiap judul, paragraf, foto, dan tombol adalah blok terpisah yang bisa
   diklik lalu diedit langsung, digeser urutannya, dihapus, atau ditambah
   blok baru dari tombol **+**.
4. Setelah selesai, klik **Update** (atau **Publish**) di kanan atas.

Tidak perlu plugin tambahan apa pun — semua sudah bisa diedit dengan editor
bawaan WordPress ini. Kalau di situs ini plugin **Elementor** kebetulan
sudah/nanti terpasang, halaman yang sama juga bisa dibuka lewat
**Edit with Elementor** — datanya sudah disiapkan juga, jadi tidak akan
kosong.

## Referensi tambahan
- `04-content/` — salinan teks isi website dalam format JSON/teks, buat
  referensi kalau butuh copy-paste ulang.
- `05-assets/` — catatan aset milik client (logo, dll).

## Kalau ada masalah
- **Upload theme/plugin gagal "The uploaded file exceeds..."** — biasanya
  batas ukuran upload hosting terlalu kecil; hubungi provider hosting untuk
  menaikkan `upload_max_filesize`, atau upload manual lewat FTP/File
  Manager ke folder `wp-content/themes/` (untuk theme) dan
  `wp-content/plugins/` (untuk plugin), lalu extract ZIP-nya di sana, baru
  aktifkan lewat halaman Themes/Plugins seperti biasa.
- **Halaman kosong / foto tidak muncul** — buka halamannya di Block Editor,
  foto biasanya butuh beberapa detik untuk ter-upload otomatis saat plugin
  pertama kali aktif; refresh halaman **Pages** sekali lagi.
- **Salah upload** (misalnya file plugin di-upload ke menu Themes) — WordPress
  akan menolak dengan pesan error "not a valid ZIP" atau "missing style
  sheet"; ulangi dari Langkah 2/3 dengan file yang benar.
MD;
    }

    private function addComponentArchive(ZipArchive $bundleZip, string $archivePath, array $files): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'wp-component-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('File sementara untuk ZIP component tidak dapat dibuat.');
        }

        $componentZip = new ZipArchive();
        if ($componentZip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            throw new \RuntimeException('ZIP component tidak dapat dibuat.');
        }

        foreach ($files as $relativePath => $contents) {
            $componentZip->addFromString($relativePath, $contents);
        }

        $componentZip->close();
        $bundleZip->addFile($temporaryPath, $archivePath);

        return $temporaryPath;
    }

    /**
     * Appends a deterministic, hand-written importer to the plugin (not
     * AI-generated, so it's never subject to the AI builder getting the
     * WordPress page-creation APIs slightly wrong): on first admin page load
     * after activation it creates a real WP Page for every page in the
     * approved mockup, with its content already written as native Gutenberg
     * blocks — so every page opens ready to edit in WordPress's built-in
     * Block Editor — no extra plugin required. Each page's real Elementor
     * data is also set (see ElementorPageBuilderService), inert unless the
     * Elementor plugin happens to be installed, in which case "Edit with
     * Elementor" opens the real page too instead of a blank draft.
     */
    private function injectPageImporter(array $bundle, array &$pluginFiles, string $pluginRoot): void
    {
        // Always make our own code the plugin's real entry point (the file
        // WordPress reads the "Plugin Name:" header from), moving whatever
        // the AI wrote into a secondary include loaded defensively. This
        // project has repeatedly hit AI-generated PHP that doesn't match
        // the exact shape/validity expected — if the AI's file has a bug
        // and stays the entry point, page creation (appended to the end of
        // its content) may simply never run because PHP never reaches that
        // line. Making our deterministic code the entry point means page
        // creation runs regardless of what the AI wrote.
        $this->stabilizePluginMainFile($pluginFiles, $pluginRoot);

        $pages = $bundle['elementor_pages'] ?? [];
        if (!$pages || !is_array($pages)) {
            return;
        }

        $pagesForExport = [];
        foreach ($pages as $slug => $page) {
            if (!is_string($slug) || !is_array($page)) {
                continue;
            }
            $pagesForExport[$slug] = [
                'title' => (string) ($page['title'] ?? ucfirst($slug)),
                'html' => (string) ($page['html'] ?? ''),
                // Real Elementor "classic" section/column/widget tree (see
                // ElementorPageBuilderService::mapSectionsToElements). Only
                // takes effect if the Elementor plugin is later installed —
                // set as _elementor_data below so "Edit with Elementor"
                // shows the real page instead of an empty draft; ignored
                // entirely otherwise, when the native Block Editor content
                // (html, above) is what's shown.
                'elements' => is_array($page['elements'] ?? null) ? $page['elements'] : [],
            ];
        }

        if (!$pagesForExport) {
            return;
        }

        // SectionImageService's generated photos (filename => raw PNG
        // bytes). Embedded as real binary files in the plugin and uploaded
        // to the Media Library at import time — see
        // exito_client_import_images() below.
        $images = $bundle['section_images'] ?? [];
        $imagesForExport = [];
        if (is_array($images)) {
            foreach ($images as $filename => $bytes) {
                if (!is_string($filename) || !is_string($bytes) || $bytes === '') {
                    continue;
                }
                $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'image.png';
                $pluginFiles[$pluginRoot . '/images/' . $safeFilename] = $bytes;
                $imagesForExport[$safeFilename] = 'images/' . $safeFilename;
            }
        }

        $exportedPages = var_export($pagesForExport, true);
        $exportedImages = var_export($imagesForExport, true);

        $pluginFiles[$pluginRoot . '/includes/page-import.php'] = <<<PHP
<?php
if (!defined('ABSPATH')) { exit; }

function exito_client_pages() {
    return {$exportedPages};
}

function exito_client_images() {
    return {$exportedImages};
}

// Uploads every generated photo into the Media Library and returns a
// filename => attachment URL map. A photo that fails to generate/upload is
// simply missing from the returned map (never a broken image).
function exito_client_import_images() {
    \$image_urls = [];

    if (!function_exists('wp_upload_bits')) {
        return \$image_urls;
    }
    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    if (!function_exists('wp_check_filetype')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    foreach (exito_client_images() as \$filename => \$relative_path) {
        \$full_path = plugin_dir_path(__FILE__) . '../' . \$relative_path;
        if (!file_exists(\$full_path)) {
            continue;
        }

        \$bytes = file_get_contents(\$full_path);
        if (\$bytes === false) {
            continue;
        }

        \$upload = wp_upload_bits(\$filename, null, \$bytes);
        if (!empty(\$upload['error'])) {
            continue;
        }

        \$filetype = wp_check_filetype(\$upload['file'], null);
        \$attachment_id = wp_insert_attachment([
            'post_mime_type' => \$filetype['type'],
            'post_title' => sanitize_file_name(\$filename),
            'post_status' => 'inherit',
        ], \$upload['file']);

        if (is_wp_error(\$attachment_id) || !\$attachment_id) {
            continue;
        }

        \$metadata = wp_generate_attachment_metadata(\$attachment_id, \$upload['file']);
        wp_update_attachment_metadata(\$attachment_id, \$metadata);
        \$image_urls[\$filename] = wp_get_attachment_url(\$attachment_id);
    }

    return \$image_urls;
}

// Replaces this page's __EXITO_IMAGE:<filename>__ tokens with the real
// uploaded URL, or removes the whole marked block if that photo isn't
// available (so a failed/skipped photo never leaves a broken <img>).
function exito_client_apply_images(\$html, \$image_urls) {
    return preg_replace_callback(
        '/<!--EXITO_IMG_START:(.*?)-->(.*?)<!--EXITO_IMG_END:\\1-->/s',
        function (\$matches) use (\$image_urls) {
            \$filename = \$matches[1];
            if (!isset(\$image_urls[\$filename])) {
                return '';
            }
            return str_replace('__EXITO_IMAGE:' . \$filename . '__', esc_url(\$image_urls[\$filename]), \$matches[2]);
        },
        \$html
    );
}

// Returns how many pages were actually created/updated, so the caller can
// tell a real success from a silent no-op (e.g. every wp_insert_post call
// failing) — the "already imported" flag below is only set on real success,
// so a failed run can always be retried instead of getting stuck forever.
function exito_client_import_pages() {
    if (!function_exists('wp_insert_post')) {
        return 0;
    }

    \$image_urls = exito_client_import_images();
    \$home_id = null;
    \$created = 0;

    foreach (exito_client_pages() as \$slug => \$page) {
        \$existing = get_page_by_path(\$slug);
        \$post_data = [
            'post_title' => \$page['title'],
            'post_name' => \$slug,
            'post_content' => exito_client_apply_images(\$page['html'], \$image_urls),
            'post_status' => 'publish',
            'post_type' => 'page',
        ];

        if (\$existing) {
            \$post_data['ID'] = \$existing->ID;
            \$page_id = wp_update_post(\$post_data);
        } else {
            \$page_id = wp_insert_post(\$post_data);
        }

        if (!\$page_id || is_wp_error(\$page_id)) {
            continue;
        }

        \$created++;

        // Real Elementor page data — inert until/unless the Elementor
        // plugin is installed. When it is, "Edit with Elementor" opens
        // this real content instead of a blank draft; the theme's own
        // Block Editor content above still renders normally on the live
        // site when Elementor isn't active.
        if (!empty(\$page['elements'])) {
            update_post_meta(\$page_id, '_elementor_data', wp_slash(wp_json_encode(\$page['elements'])));
            update_post_meta(\$page_id, '_elementor_edit_mode', 'builder');
            update_post_meta(\$page_id, '_elementor_template_type', 'wp-page');
            update_post_meta(\$page_id, '_elementor_version', '3.26.0');
        }

        if (\$slug === 'home') {
            \$home_id = \$page_id;
        }
    }

    if (\$home_id) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', \$home_id);
    }

    if (\$created > 0) {
        update_option('exito_client_pages_imported', true);
    }

    return \$created;
}

// Runs once automatically the first time an admin opens wp-admin after the
// plugin is activated (the option flag stops it re-running once it has
// actually succeeded — see the \$created check above).
add_action('admin_init', function () {
    if (!get_option('exito_client_pages_imported')) {
        exito_client_import_pages();
    }

    // Manual escape hatch: ?exito_client_reimport=1 (with a valid nonce,
    // added by the admin notice below) force-runs the import again even if
    // the flag above is already set — for a site that was tested with an
    // earlier/broken version of this plugin and got the flag stuck without
    // ever actually having pages, or if pages were deleted since.
    if (
        isset(\$_GET['exito_client_reimport'])
        && current_user_can('manage_options')
        && check_admin_referer('exito_client_reimport')
    ) {
        \$count = exito_client_import_pages();
        add_action('admin_notices', function () use (\$count) {
            \$message = \$count > 0
                ? sprintf('Exito Core: %d halaman berhasil dibuat/diperbarui.', \$count)
                : 'Exito Core: tidak ada halaman yang berhasil dibuat. Cek error log server untuk detail.';
            echo '<div class="notice notice-' . (\$count > 0 ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html(\$message) . '</p></div>';
        });
    }
});

// Always-available manual trigger, in case the automatic run above never
// succeeded (e.g. a stuck flag left over from testing an earlier version of
// this plugin on the same site).
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    \$url = wp_nonce_url(add_query_arg('exito_client_reimport', '1'), 'exito_client_reimport');
    echo '<div class="notice notice-info"><p><strong>Exito Core</strong> — kalau halaman Home/About/Services/Contact belum muncul di menu Pages, klik: <a href="' . esc_url(\$url) . '">Buat/perbarui halaman sekarang</a>. Halaman-halaman itu langsung bisa diedit lewat Block Editor bawaan WordPress, dan lewat Elementor juga kalau plugin Elementor aktif.</p></div>';
});

// Baseline styling for the native Gutenberg block content these pages are
// built from (headings, paragraphs, columns, buttons), using the approved
// brand colors — makes the block editor's default output look intentional
// without needing any additional plugin or theme.
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('exito-client-blocks', plugin_dir_url(__FILE__) . '../assets/block-content.css', [], '1.0.0');
});

// Load the same stylesheet inside the Block Editor's own preview (not just
// the live site) — without this, WordPress shows plain/unstyled text while
// editing a page even though the real front-end page is styled fine, which
// reads as "the theme disappeared" the moment you click Edit. Registered
// from the plugin (not left to the AI-written theme) so this always works
// no matter what functions.php does or doesn't do.
add_action('after_setup_theme', function () {
    add_theme_support('editor-styles');
    add_editor_style(plugin_dir_url(__FILE__) . '../assets/block-content.css');
});
PHP;

        $pluginFiles[$pluginRoot . '/assets/block-content.css'] = $this->blockContentCss($bundle['brand'] ?? []);
    }

    /**
     * Makes `{$pluginRoot}/{$pluginRoot}.php` (the file WordPress reads the
     * plugin header from) our own deterministic bootstrap: it always
     * requires `includes/page-import.php` first — unconditionally, nothing
     * can skip it — then, only if the AI produced a main-file of its own,
     * loads that as `includes/ai-plugin-extra.php` wrapped in a try/catch.
     * A runtime error thrown from the AI's code is caught and ignored,
     * so it can never stop the page-creation logic above it from running.
     * (A hard PHP *parse* error in the AI file would still be fatal if that
     * file is ever required directly — but `require`d files are compiled
     * lazily, so PHP raises that as a catchable \ParseError right here,
     * not at the top of this file, which is exactly what the try/catch
     * below is for.)
     */
    private function stabilizePluginMainFile(array &$pluginFiles, string $pluginRoot): void
    {
        $mainFileKey = $pluginRoot . '/' . $pluginRoot . '.php';
        $aiMainFile = $pluginFiles[$mainFileKey] ?? null;
        unset($pluginFiles[$mainFileKey]);

        if (is_string($aiMainFile) && trim($aiMainFile) !== '') {
            $pluginFiles[$pluginRoot . '/includes/ai-plugin-extra.php'] = $aiMainFile;
        }

        $pluginFiles[$mainFileKey] = $this->pluginBootstrap($pluginRoot, isset($pluginFiles[$pluginRoot . '/includes/ai-plugin-extra.php']));
    }

    private function pluginBootstrap(string $pluginRoot, bool $hasAiExtra): string
    {
        $name = ucwords(str_replace('-', ' ', $pluginRoot));

        $extraInclude = $hasAiExtra
            ? <<<'PHP'


// Optional extra behaviour the AI builder generated for this plugin. Loaded
// defensively — a runtime error here is caught and ignored, so it can never
// stop the page creation above from having already run.
if (file_exists(__DIR__ . '/includes/ai-plugin-extra.php')) {
    try {
        require_once __DIR__ . '/includes/ai-plugin-extra.php';
    } catch (\Throwable $exito_client_extra_error) {
        // Intentionally ignored — see comment above.
    }
}
PHP
            : '';

        return <<<PHP
<?php
/**
 * Plugin Name: {$name}
 * Description: Creates this project's approved pages, ready to edit in the WordPress block editor. No other plugin required.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

// Deterministic and always valid: runs first, unconditionally, so page
// creation never depends on anything else in this plugin working.
if (file_exists(__DIR__ . '/includes/page-import.php')) {
    require_once __DIR__ . '/includes/page-import.php';
}
{$extraInclude}
PHP;
    }

    /**
     * Writes the client's real logo/photos (BundleBuilderService::
     * collectAssets()) into the theme at `assets/<filename>` — the exact
     * paths ClaudeWordPressBuilderService tells the AI to reference. Doing
     * this ourselves, deterministically, means
     * the real files exist in the shipped theme no matter what the AI
     * actually did with them.
     */
    /**
     * Rewrites style.css's `Version:` header to a fresh timestamp on every
     * build. functions.php enqueues style.css with
     * `wp_get_theme()->get('Version')` as its cache-busting query string
     * (`style.css?ver=...`) — the AI always writes a static "1.0.0" there,
     * so re-uploading a rebuilt theme with real CSS changes can still show
     * a browser/proxy-cached copy of an EARLIER build's stylesheet, since
     * the URL never changes. This makes it change every time regardless of
     * what the AI wrote.
     */
    private function bustThemeStyleVersion(array &$themeFiles, string $themeRoot): void
    {
        $styleKey = $themeRoot . '/style.css';
        if (!isset($themeFiles[$styleKey]) || !is_string($themeFiles[$styleKey])) {
            return;
        }

        $version = '1.0.' . now()->format('YmdHis');
        $updated = preg_replace('/^(\s*Version:\s*).+$/mi', '${1}' . $version, $themeFiles[$styleKey], 1, $count);

        if ($count > 0 && is_string($updated)) {
            $themeFiles[$styleKey] = $updated;
        }
    }

    private function embedThemeAssets(array $bundle, array &$themeFiles, string $themeRoot): void
    {
        $assets = $bundle['assets'] ?? [];
        if (!is_array($assets)) {
            return;
        }

        $logo = $assets['logo'] ?? null;
        if (is_array($logo) && !empty($logo['filename']) && is_string($logo['bytes'] ?? null)) {
            $themeFiles[$themeRoot . '/assets/' . $logo['filename']] = $logo['bytes'];
        }

        foreach ($assets['images'] ?? [] as $image) {
            if (is_array($image) && !empty($image['filename']) && is_string($image['bytes'] ?? null)) {
                $themeFiles[$themeRoot . '/assets/' . $image['filename']] = $image['bytes'];
            }
        }
    }

    private function blockContentCss(array $brand): string
    {
        $primary = $this->safeHexColor($brand['primary_color'] ?? null, '#1F2937');
        $accent = $this->safeHexColor($brand['accent_color'] ?? null, '#2563EB');

        return <<<CSS
.wp-block-heading { color: {$primary}; }
.wp-block-button__link, .wp-element-button { background-color: {$accent}; color: #fff; border-radius: 8px; padding: 12px 22px; font-weight: 600; }
.wp-block-columns { gap: 24px; margin: 24px 0; }
.wp-block-column { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; }
.wp-block-separator { border-color: #e5e7eb; margin: 40px auto; max-width: 1100px; }
.wp-block-image img { border-radius: 12px; object-fit: cover; width: 100%; height: auto; }
.wp-block-column .wp-block-image { margin-bottom: 12px; }
.entry-content, .site-content, main { max-width: 1100px; margin-left: auto; margin-right: auto; padding: 0 24px; }
CSS;
    }

    private function safeHexColor(?string $color, string $fallback): string
    {
        $color = ltrim((string) $color, '#');
        if (strlen($color) === 6 && ctype_xdigit($color)) {
            return '#' . $color;
        }

        return $fallback;
    }

}
