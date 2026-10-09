<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BundleExporterService
{
    public function export(array $bundle, string $outputDir): string
    {
        if (! is_dir($outputDir) && ! mkdir($outputDir, 0777, true) && ! is_dir($outputDir)) {
            throw new \RuntimeException('Folder export bundle tidak dapat dibuat.');
        }

        $themeRoot = trim((string) ($bundle['theme']['name'] ?? 'exito-client-theme'), '/');
        $themeFiles = $this->buildCompleteThemeFiles($bundle, $themeRoot);

        // theme-install.zip is now the ONLY file that actually needs to be
        // uploaded to WordPress: the approved pages, their content, and
        // every generated photo are already baked into it (see
        // injectPageImporterIntoTheme()) — install this one theme,
        // activate it, done. No separate plugin.
        $themeInstallPath = $outputDir.DIRECTORY_SEPARATOR.'theme-install.zip';
        $themeZip = new ZipArchive;
        if ($themeZip->open($themeInstallPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP theme WordPress tidak dapat dibuat.');
        }
        foreach ($themeFiles as $relativePath => $contents) {
            $themeZip->addFromString($relativePath, $contents);
        }
        $themeZip->close();

        // bundle-export.zip wraps that same theme zip together with a
        // README and text/JSON copies of the content & client assets for
        // reference — nice to have, but nothing inside it besides the
        // theme zip is ever uploaded anywhere.
        $zipPath = $outputDir.DIRECTORY_SEPARATOR.'bundle-export.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('File ZIP bundle tidak dapat dibuat.');
        }

        $zip->addFile($themeInstallPath, '01-theme/'.$themeRoot.'.zip');

        $files = [
            'content' => $bundle['content'] ?? [],
            'assets' => $bundle['assets'] ?? [],
        ];

        foreach ($files as $folder => $payload) {
            $targetFolder = $folder === 'assets' ? '03-assets' : '02-content';
            $zip->addFromString($targetFolder.'/README.txt', "Bundle component: {$folder}\n");
            if (is_array($payload)) {
                foreach ($payload as $key => $value) {
                    if (is_array($value)) {
                        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $zip->addFromString($targetFolder.'/'.$key.'.json', $json);
                    } else {
                        $zip->addFromString($targetFolder.'/'.$key.'.txt', (string) $value);
                    }
                }
            }
        }

        $zip->addFromString('03-assets/README.txt', "Copy client assets to the WordPress media library during setup.\n");
        $zip->addFromString('README.md', $this->buildReadme($themeRoot));
        $zip->close();

        // theme-install.zip is the real deliverable — the one file that
        // gets uploaded to WordPress. bundle-export.zip (built above) just
        // wraps it with reference material for anyone who wants that too.
        return $themeInstallPath;
    }

    /**
     * Assembles the final theme file set: the AI builder's chrome
     * (style.css/header.php/footer.php/etc.), a generated screenshot, the
     * deterministic page/photo importer (injectPageImporterIntoTheme —
     * this is what makes the theme self-sufficient, no separate plugin),
     * the client's real logo/photos, and a fresh style.css cache-busting
     * version. Used for both theme-install.zip and the copy embedded in
     * bundle-export.zip, so they're never able to drift apart.
     */
    private function buildCompleteThemeFiles(array $bundle, string $themeRoot): array
    {
        $wordpressFiles = $bundle['wordpress']['files'] ?? [];
        $themeFiles = [];

        foreach ($wordpressFiles as $relativePath => $contents) {
            if (is_string($relativePath) && is_string($contents)) {
                $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
                if (str_starts_with($relativePath, $themeRoot.'/')) {
                    $themeFiles[$relativePath] = $contents;
                }
            }
        }

        if (empty($themeFiles)) {
            throw new \RuntimeException('File theme WordPress tidak ditemukan dalam hasil build.');
        }

        // This theme is now the ONLY file the client installs — there is
        // no separate plugin left as a fallback. WordPress refuses to
        // install a theme whose style.css has no `Theme Name:` header, the
        // exact same class of failure ("invalid header") this project has
        // already hit once for the old plugin — guarantee it deterministically
        // instead of trusting the AI remembered to include one.
        $this->ensureThemeHeader($themeFiles, $themeRoot, $bundle);

        $themeFiles[$themeRoot.'/screenshot.png'] = $this->themeScreenshot($bundle);

        // The client's real logo/photos (BundleBuilderService::collectAssets),
        // written into the theme at the exact same fixed paths the AI
        // builder prompts were told to reference — guarantees the real
        // files exist regardless of what the AI actually did with them.
        $this->embedThemeAssets($bundle, $themeFiles, $themeRoot);

        // Render pages inside the same approved mockup shell and stylesheet.
        // Keep the markup in post_content as a core HTML block so it remains
        // available in the Block Editor while its frontend output stays exact.
        $this->enforceMockupTheme($bundle, $themeFiles, $themeRoot);

        // Deterministic (non-AI) page + photo importer, appended straight
        // into functions.php — see injectPageImporterIntoTheme()'s
        // docblock for why this lives in the theme instead of a plugin.
        $this->injectPageImporterIntoTheme($bundle, $themeFiles, $themeRoot);

        $this->bustThemeStyleVersion($themeFiles, $themeRoot);

        return $themeFiles;
    }

    /**
     * WordPress reads a theme's identity from a `Theme Name:` header inside
     * the first ~8KB of style.css (get_file_data(), the same mechanism a
     * plugin's `Plugin Name:` header uses) — install fails with a generic
     * "invalid header"-style error if it's missing. Checked with the same
     * regex WordPress itself uses; if genuinely absent, a valid header is
     * prepended without touching whatever CSS rules the AI already wrote.
     */
    private function ensureThemeHeader(array &$themeFiles, string $themeRoot, array $bundle): void
    {
        $styleKey = $themeRoot.'/style.css';
        $existing = is_string($themeFiles[$styleKey] ?? null) ? $themeFiles[$styleKey] : '';

        if (preg_match('/^[ \t\/*#@]*Theme Name:(.*)$/mi', substr($existing, 0, 8192))) {
            return;
        }

        $name = (string) data_get($bundle, 'brand.company_name', '');
        $name = str_replace('*/', '', trim($name));
        $name = $name !== '' ? $name.' — Exito Client Theme' : ucwords(str_replace('-', ' ', $themeRoot));

        $header = <<<CSS
/*
Theme Name: {$name}
Description: Approved client website, generated by Exito.
Version: 1.0.0
Requires at least: 5.9
Requires PHP: 7.4
*/

CSS;

        $themeFiles[$styleKey] = $header.$existing;
    }

    private function themeScreenshot(array $bundle): string
    {
        $approvedScreenshot = ltrim((string) data_get($bundle, 'mockup.screenshot_path', ''), '/');
        $disk = Storage::disk('public');
        if ($approvedScreenshot !== '' && ! str_contains($approvedScreenshot, '..') && $disk->exists($approvedScreenshot)) {
            return (string) $disk->get($approvedScreenshot);
        }

        if (! function_exists('imagecreatetruecolor')) {
            return '';
        }

        $brand = $bundle['brand'] ?? [];
        $content = $bundle['content'] ?? [];
        $title = (string) data_get($content, 'hero.title', $brand['company_name'] ?? 'Client Website');
        $primary = (string) ($brand['primary_color'] ?? '#1F2937');
        $primary = ltrim($primary, '#');
        if (strlen($primary) !== 6 || ! ctype_xdigit($primary)) {
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

    /** Replace the separately generated AI shell with the approved mockup shell. */
    private function enforceMockupTheme(array $bundle, array &$themeFiles, string $themeRoot): void
    {
        $styleKey = $themeRoot.'/style.css';
        $header = '';
        if (isset($themeFiles[$styleKey]) && preg_match('/\A.*?(?=\n\s*\*\/)/s', $themeFiles[$styleKey], $match)) {
            $header = rtrim($match[0])."\n */\n\n";
        }
        if ($header === '') {
            $this->ensureThemeHeader($themeFiles, $themeRoot, $bundle);
            $existing = $themeFiles[$styleKey] ?? '';
            preg_match('/\A\/\*.*?\*\/\s*/s', $existing, $match);
            $header = $match[0] ?? '';
        }

        $css = (string) data_get($bundle, 'mockup_rendering.css', '');
        if ($css !== '') {
            $themeFiles[$styleKey] = $header.$css;
        }

        $themeFiles[$themeRoot.'/functions.php'] = "<?php\nadd_action('after_setup_theme', function () { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('editor-styles'); add_editor_style('style.css'); });\n";
        $themeFiles[$themeRoot.'/header.php'] = <<<'PHP'
<!doctype html>
<html lang="__EXITO_LANGUAGE__">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($GLOBALS['exito_mockup_fonts_url'])): ?><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="<?php echo esc_url($GLOBALS['exito_mockup_fonts_url']); ?>"><?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class('is-fluid'); ?>>
<?php wp_body_open(); ?>
PHP;
        $fontsUrl = (string) data_get($bundle, 'mockup_rendering.fonts_url', '');
        $language = data_get($bundle, 'mockup_rendering.lang') === 'en' ? 'en' : 'id';
        $themeFiles[$themeRoot.'/header.php'] = str_replace('__EXITO_LANGUAGE__', $language, $themeFiles[$themeRoot.'/header.php']);
        $themeFiles[$themeRoot.'/functions.php'] .= "\n\$GLOBALS['exito_mockup_fonts_url'] = ".var_export($fontsUrl, true).";\n";
        $themeFiles[$themeRoot.'/footer.php'] = "<?php wp_footer(); ?>\n</body>\n</html>\n";
        $template = <<<'PHP'
<?php get_header(); ?>
<?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
<?php get_footer(); ?>
PHP;
        $themeFiles[$themeRoot.'/page.php'] = $template;
        $themeFiles[$themeRoot.'/front-page.php'] = $template;
        $themeFiles[$themeRoot.'/index.php'] = $template;
    }

    /**
     * Step-by-step install guide in Bahasa Indonesia, written for someone
     * installing this for the first time — a single theme upload, nothing
     * else. There used to be a second "install & activate the plugin" step
     * here; that logic now lives inside the theme itself (see
     * injectPageImporterIntoTheme()), so there's one fewer upload for the
     * client to get right, and one fewer thing that can fail with a
     * misleading "invalid header" error from a separate plugin zip.
     */
    private function buildReadme(string $themeRoot): string
    {
        $themeZip = $themeRoot.'.zip';

        return <<<MD
# Cara Install Website WordPress Ini

Cuma ada **1 file** yang perlu kamu upload ke WordPress: file theme di dalam
folder `01-theme/`. Folder `02-content/` dan `03-assets/` isinya cuma salinan
teks/aset untuk referensi — tidak perlu diupload kemana pun.

```
bundle-export.zip           <- ZIP besar yang kamu download, extract dulu
Ã¢â€Å“Ã¢â€â‚¬ 01-theme/
Ã¢â€â€š  Ã¢â€â€Ã¢â€â‚¬ {$themeZip}       <- SATU-SATUNYA file yang diupload ke WordPress
Ã¢â€Å“Ã¢â€â‚¬ 02-content/                <- referensi teks isi web (tidak perlu diupload)
Ã¢â€Å“Ã¢â€â‚¬ 03-assets/                 <- referensi aset client (tidak perlu diupload)
Ã¢â€â€Ã¢â€â‚¬ README.md                  <- file ini
```

## Langkah 1 — Extract dulu
Klik kanan `bundle-export.zip` (atau file `project-...-wordpress-theme.zip`
yang kamu download dari dashboard) Ã¢â€ â€™ **Extract Here / Extract All**.

## Langkah 2 — Pasang Theme (satu-satunya langkah install)
1. Login ke **WordPress Admin** (`namadomain.com/wp-admin`).
2. Buka menu **Appearance > Themes**.
3. Klik **Add New Theme** (di bagian atas halaman) Ã¢â€ â€™ **Upload Theme**.
4. Klik **Choose File**, cari folder hasil extract tadi, masuk ke folder
   `01-theme/`, pilih file **`{$themeZip}`**.
5. Klik **Install Now**, tunggu sampai selesai, lalu klik **Activate**.

Tidak ada langkah plugin. Tidak ada langkah lain.

## Langkah 3 — Selesai, halaman sudah otomatis jadi
Begitu theme aktif, semua halaman (Home, About, Services, Contact, dst)
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

Kalau setelah theme aktif halaman-halaman itu belum muncul, buka sembarang
halaman lain di WordPress Admin (misalnya klik menu Dashboard) lalu cek lagi
menu Pages — proses pembuatan halaman jalan otomatis begitu ada admin yang
membuka wp-admin setelah theme aktif. Ada juga notifikasi kuning di atas
wp-admin dengan tombol "Buat/perbarui halaman sekarang" kalau perlu dipicu
manual.

## Langkah 4 — Edit isi halaman
Untuk mengubah teks, foto, atau urutan section:
1. Buka menu **Pages**, klik halaman yang mau diedit (misalnya "Home").
2. Klik **Edit** — akan terbuka **Block Editor** bawaan WordPress.
3. Klik blok HTML untuk mengubah isi halaman. Markup di dalamnya mengikuti
   mockup yang dipilih. Perubahan urutan section atau tampilannya perlu
   dilakukan pada markup tersebut.
4. Setelah selesai, klik **Update** (atau **Publish**) di kanan atas.

Tidak perlu plugin tambahan. Susunan visual halaman mengikuti mockup yang
dipilih. Ubah markup pada blok HTML di Block Editor untuk menyesuaikan halaman.

## Referensi tambahan
- `02-content/` — salinan teks isi website dalam format JSON/teks, buat
  referensi kalau butuh copy-paste ulang.
- `03-assets/` — catatan aset milik client (logo, dll).

## Kalau ada masalah
- **Upload theme gagal "The uploaded file exceeds..."** — biasanya batas
  ukuran upload hosting terlalu kecil; hubungi provider hosting untuk
  menaikkan `upload_max_filesize`/`post_max_size`, atau upload manual lewat
  FTP/File Manager: extract `{$themeZip}` di komputer, lalu upload folder
  hasil extract-nya langsung ke `wp-content/themes/` di server, baru
  aktifkan lewat menu **Appearance > Themes** seperti biasa.
- **Halaman kosong / foto tidak muncul** — buka halamannya di Block Editor,
  foto biasanya butuh beberapa detik untuk ter-upload otomatis saat theme
  pertama kali aktif; refresh halaman **Pages** sekali lagi, atau pakai
  tombol "Buat/perbarui halaman sekarang" di notifikasi admin.
MD;
    }

    /**
     * Appends a deterministic, hand-written page/photo importer directly
     * into the theme's functions.php — NOT a separate plugin. Runs the
     * first time an admin opens wp-admin after the THEME is activated: it
     * creates one WP Page per approved mockup page. The shared mockup markup
     * is stored in a core HTML block so the installed theme renders the same
     * body, styles, and photos as the preview. Reimport also resolves image
     * and page-link tokens after every page exists.
     *
     * This used to live in a separate "exito-core" plugin the client had to
     * install and activate as a second step after the theme. That was both
     * an extra step or the client to get wrong/forget, and one more upload
     * that could hit a host's upload-size limit and fail with WordPress's
     * misleading "The plugin does not have a valid header" — folding it
     * into the theme means installing/activating the theme is the ONLY
     * step; there is nothing else to upload.
     */
    private function injectPageImporterIntoTheme(array $bundle, array &$themeFiles, string $themeRoot): void
    {
        $pages = $bundle['elementor_pages'] ?? [];
        if (! $pages || ! is_array($pages)) {
            return;
        }

        $pagesForExport = [];
        foreach ($pages as $slug => $page) {
            if (! is_string($slug) || ! is_array($page)) {
                continue;
            }
            $pagesForExport[$slug] = [
                'title' => (string) ($page['title'] ?? ucfirst($slug)),
                'html' => (string) data_get($bundle, "mockup_rendering.pages.{$slug}.html", $page['html'] ?? ''),
                // Keep legacy Elementor data only for bundles without shared mockup markup.
                'elements' => data_get($bundle, "mockup_rendering.pages.{$slug}.html")
                    ? []
                    : (is_array($page['elements'] ?? null) ? $page['elements'] : []),
            ];
        }

        if (! $pagesForExport) {
            return;
        }

        // The approved mockup's own photos (filename => raw JPEG bytes),
        // read back off disk by MockupAssetService::loadApproved(). Embedded
        // verbatim as real binary files in the theme and uploaded to the Media
        // Library at import time — see exito_client_import_images() below.
        $images = $bundle['section_images'] ?? [];
        $imagesForExport = [];
        if (is_array($images)) {
            foreach ($images as $filename => $bytes) {
                if (! is_string($filename) || ! is_string($bytes) || $bytes === '') {
                    continue;
                }
                $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'image.png';
                $themeFiles[$themeRoot.'/generated-images/'.$safeFilename] = $bytes;
                $imagesForExport[$safeFilename] = 'generated-images/'.$safeFilename;
            }
        }

        $logo = $bundle['assets']['logo'] ?? null;
        if (is_array($logo) && ! empty($logo['filename']) && is_string($logo['bytes'] ?? null) && $logo['bytes'] !== '') {
            $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '-', $logo['filename']) ?: 'client-logo.png';
            $themeFiles[$themeRoot.'/assets/'.$safeFilename] = $logo['bytes'];
            $imagesForExport[$safeFilename] = 'assets/'.$safeFilename;
        }

        $exportedPages = var_export($pagesForExport, true);
        $exportedImages = var_export($imagesForExport, true);
        // Identifies THIS build's content — changes any time the approved
        // mockup/photos are rebuilt. Lets the importer below tell "never
        // imported yet" apart from "imported an OLDER version of this
        // content", so re-uploading a rebuilt theme (e.g. a fixed layout)
        // actually refreshes the live pages instead of leaving whatever an
        // earlier build already created untouched forever.
        $contentVersion = var_export(md5($exportedPages.$exportedImages), true);
        $importOwner = var_export('exito:'.($bundle['project_id'] ?? $themeRoot), true);

        // A trailing PHP closing tag in whatever functions.php content
        // already exists (the AI builder's, or a prior call's) would turn
        // everything we append after it into literal HTML output instead
        // of PHP — strip it defensively before appending, same as
        // WordPress core itself recommends omitting the closing tag
        // entirely.
        $existingFunctions = rtrim($themeFiles[$themeRoot.'/functions.php'] ?? "<?php\n");
        $existingFunctions = preg_replace('/\?>\s*$/', '', $existingFunctions);

        $importerCode = <<<PHP


// ---------------------------------------------------------------------
// Automatic page & photo import — added at build time (not AI-written).
// Runs the first time an admin opens wp-admin after this theme is active.
// ---------------------------------------------------------------------
function exito_client_pages() {
    return {$exportedPages};
}

function exito_client_images() {
    return {$exportedImages};
}

function exito_client_content_version() {
    return {$contentVersion};
}

function exito_client_page_key(\$slug) {
    return {$importOwner} . ':' . \$slug;
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
        \$full_path = get_stylesheet_directory() . '/' . \$relative_path;
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
    \$html = preg_replace_callback(
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

    // Tokens outside the markers — a cover block's `url` attribute in its
    // block comment — must match the <img> the markers produced, or the Block
    // Editor flags the block invalid. A photo that never arrived becomes an
    // empty url, which cover renders as a plain colour band.
    return preg_replace_callback(
        '/__EXITO_IMAGE:(.*?)__/',
        function (\$matches) use (\$image_urls) {
            return isset(\$image_urls[\$matches[1]]) ? esc_url(\$image_urls[\$matches[1]]) : '';
        },
        \$html
    );
}

// Returns how many pages were actually created/updated, so the caller can
// tell a real success from a silent no-op (e.g. every wp_insert_post call
// failing) — the stored content-version option below is only advanced on
// real success, so a failed run can always be retried instead of getting
// stuck forever.
function exito_client_import_pages() {
    if (!function_exists('wp_insert_post')) {
        return 0;
    }

    \$image_urls = exito_client_import_images();
    \$home_id = null;
    \$created = 0;
    \$page_ids = [];

    foreach (exito_client_pages() as \$slug => \$page) {
        \$owned_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_key' => '_exito_client_page_key',
            'meta_value' => exito_client_page_key(\$slug),
        ]);
        \$existing_id = \$owned_pages ? (int) \$owned_pages[0] : 0;
        \$post_slug = \$existing_id ? get_post_field('post_name', \$existing_id) : \$slug;

        if (!\$existing_id && get_page_by_path(\$slug)) {
            \$post_slug = wp_unique_post_slug(\$slug, 0, 'publish', 'page', 0);
        }

        \$post_data = [
            'post_title' => \$page['title'],
            'post_name' => \$post_slug,
            'post_content' => exito_client_apply_images(\$page['html'], \$image_urls),
            'post_status' => 'publish',
            'post_type' => 'page',
            'meta_input' => ['_exito_client_page_key' => exito_client_page_key(\$slug)],
        ];

        if (\$existing_id) {
            \$post_data['ID'] = \$existing_id;
            \$page_id = wp_update_post(\$post_data);
        } else {
            \$page_id = wp_insert_post(\$post_data);
        }

        if (!\$page_id || is_wp_error(\$page_id)) {
            continue;
        }

        \$created++;
        \$page_ids[\$slug] = (int) \$page_id;

        // Legacy builds can retain their Elementor content. Current mockup
        // builds clear this metadata to prevent a second renderer changing
        // the approved page layout.
        if (!empty(\$page['elements'])) {
            update_post_meta(\$page_id, '_elementor_data', wp_slash(wp_json_encode(\$page['elements'])));
            update_post_meta(\$page_id, '_elementor_edit_mode', 'builder');
            update_post_meta(\$page_id, '_elementor_template_type', 'wp-page');
            update_post_meta(\$page_id, '_elementor_version', '3.26.0');
        } else {
            delete_post_meta(\$page_id, '_elementor_data');
            delete_post_meta(\$page_id, '_elementor_edit_mode');
            delete_post_meta(\$page_id, '_elementor_template_type');
            delete_post_meta(\$page_id, '_elementor_version');
        }

        if (\$slug === 'home') {
            \$home_id = \$page_id;
        }
    }

    foreach (exito_client_pages() as \$slug => \$page) {
        \$post_id = \$page_ids[\$slug] ?? 0;
        \$post = \$post_id ? get_post(\$post_id) : null;
        if (!\$post) {
            continue;
        }
        \$html = str_replace('__EXITO_PAGE_URL:home__', esc_url(home_url('/')), \$post->post_content);
        \$html = preg_replace_callback('/__EXITO_PAGE_URL:([a-z0-9-]+)__/', function (\$matches) use (\$page_ids) {
            \$target_id = \$page_ids[\$matches[1]] ?? 0;
            \$target = \$target_id ? get_post(\$target_id) : null;
            return \$target ? esc_url(get_permalink(\$target)) : esc_url(home_url('/'));
        }, \$html);
        if (\$html !== \$post->post_content) {
            wp_update_post(['ID' => \$post->ID, 'post_content' => \$html]);
        }
    }

    if (\$home_id) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', \$home_id);
    }

    if (\$created > 0) {
        update_option('exito_client_pages_imported', exito_client_content_version());
    }

    return \$created;
}

// Runs automatically the first time an admin opens wp-admin after this
// theme is activated, AND again any time a rebuilt theme with different
// content (a fixed layout, updated copy, new photos, ...) is uploaded and
// activated — compares the stored option against this build's content
// version instead of a plain yes/no flag, so re-uploading a newer theme
// always refreshes the live pages instead of leaving an older build's
// content stuck forever (see the \$created check above for when the stored
// version actually advances).
add_action('admin_init', function () {
    if (get_option('exito_client_pages_imported') !== exito_client_content_version()) {
        exito_client_import_pages();
    }

    // Manual escape hatch: ?exito_client_reimport=1 (with a valid nonce,
    // added by the admin notice below) force-runs the import again even if
    // the flag above is already set — for a site that was tested with an
    // earlier/broken version of this theme and got the flag stuck without
    // ever actually having pages, or if pages were deleted since.
    if (
        isset(\$_GET['exito_client_reimport'])
        && current_user_can('manage_options')
        && check_admin_referer('exito_client_reimport')
    ) {
        \$count = exito_client_import_pages();
        add_action('admin_notices', function () use (\$count) {
            \$message = \$count > 0
                ? sprintf('Exito: %d halaman berhasil dibuat/diperbarui.', \$count)
                : 'Exito: tidak ada halaman yang berhasil dibuat. Cek error log server untuk detail.';
            echo '<div class="notice notice-' . (\$count > 0 ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html(\$message) . '</p></div>';
        });
    }
});

// Always-available manual trigger, in case the automatic run above never
// succeeded (e.g. a stuck flag left over from testing an earlier version of
// this theme on the same site).
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    \$url = wp_nonce_url(add_query_arg('exito_client_reimport', '1'), 'exito_client_reimport');
    echo '<div class="notice notice-info"><p><strong>Exito</strong> — kalau halaman Home/About/Services/Contact belum muncul di menu Pages, klik: <a href="' . esc_url(\$url) . '">Buat/perbarui halaman sekarang</a>. Halaman mengikuti mockup dan isinya bisa diedit lewat blok HTML di Block Editor.</p></div>';
});

// Baseline styling for the native Gutenberg block content these pages are
// built from (headings, paragraphs, columns, buttons), using the approved
// brand colors — makes the block editor's default output look intentional
// without needing any additional plugin.

// Load the same stylesheet inside the Block Editor's own preview (not just
// the live site) — without this, WordPress shows plain/unstyled text while
// editing a page even though the real front-end page is styled fine, which
// reads as "the theme disappeared" the moment you click Edit.

PHP;

        $themeFiles[$themeRoot.'/functions.php'] = $existingFunctions.$importerCode;
    }

    /**
     * Writes the client's real logo/photos (BundleBuilderService::
     * collectAssets()) into the theme at `assets/<filename>` — the exact
     * paths OpenAiWordPressBuilderService tells the AI to reference. Doing
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
        $styleKey = $themeRoot.'/style.css';
        if (! isset($themeFiles[$styleKey]) || ! is_string($themeFiles[$styleKey])) {
            return;
        }

        $version = '1.0.'.now()->format('YmdHis');
        $updated = preg_replace('/^(\s*Version:\s*).+$/mi', '${1}'.$version, $themeFiles[$styleKey], 1, $count);

        if ($count > 0 && is_string($updated)) {
            $themeFiles[$styleKey] = $updated;
        }
    }

    private function embedThemeAssets(array $bundle, array &$themeFiles, string $themeRoot): void
    {
        $assets = $bundle['assets'] ?? [];
        if (! is_array($assets)) {
            return;
        }

        $logo = $assets['logo'] ?? null;
        if (is_array($logo) && ! empty($logo['filename']) && is_string($logo['bytes'] ?? null)) {
            $themeFiles[$themeRoot.'/assets/'.$logo['filename']] = $logo['bytes'];
        }

        foreach ($assets['images'] ?? [] as $image) {
            if (is_array($image) && ! empty($image['filename']) && is_string($image['bytes'] ?? null)) {
                $themeFiles[$themeRoot.'/assets/'.$image['filename']] = $image['bytes'];
            }
        }
    }

    private function blockContentCss(array $brand): string
    {
        $primary = $this->safeHexColor($brand['primary_color'] ?? null, '#1F2937');
        $accent = $this->safeHexColor($brand['accent_color'] ?? null, '#2563EB');

        return <<<CSS
.wp-block-heading { color: {$primary}; }
.wp-block-button__link, .wp-element-button { background-color: {$accent}; color: #fff; border-radius: 8px; padding: 12px 22px; font-weight: 600; text-decoration: none; display: inline-block; }
.wp-block-columns { gap: 24px; margin: 24px 0; }
.wp-block-column { padding: 8px; }
.wp-block-separator { border-color: #e5e7eb; margin: 40px auto; max-width: 1100px; }
.wp-block-image img { border-radius: 12px; object-fit: cover; width: 100%; height: auto; }
.wp-block-column .wp-block-image { margin-bottom: 12px; }
.wp-block-group.has-border-color { overflow: hidden; }
.entry-content, .site-content, main { max-width: 1100px; margin-left: auto; margin-right: auto; padding: 0 24px; }

/* Full-page sections (design renderer v2) — the finishing the approved live demo shows. */
.exito-section { margin: 0; padding-left: 24px; padding-right: 24px; }
.entry-content > .exito-section, main > .exito-section { max-width: none; margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw); padding-left: max(24px, calc(50vw - 550px)); padding-right: max(24px, calc(50vw - 550px)); }
.exito-eyebrow { font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px; }
.exito-num { font-weight: 700; letter-spacing: 1px; margin-bottom: 6px; }
.exito-feature-grid .wp-block-column { border-top: 2px solid {$primary}; padding-top: 18px; }
.exito-lead { font-size: 1.2em; line-height: 1.7; }
.exito-editorial-list { padding-left: 1.2em; }
.exito-editorial-list li { padding: 8px 0; border-top: 1px solid #e5e7eb; }
.exito-stat-value { font-size: clamp(34px, 4.4vw, 56px); line-height: 1; margin: 0; }
.exito-stat-label { opacity: .85; margin-top: 8px; }
.exito-stats-grid .wp-block-column { border-left: 1px solid currentColor; padding-left: 18px; }
.exito-quote-lead p { font-size: clamp(22px, 2.4vw, 32px); line-height: 1.35; }
.exito-quote-small { border-left: 3px solid {$accent}; padding-left: 18px; }
.exito-gallery img { aspect-ratio: 1 / 1; object-fit: cover; }
.exito-tiles .wp-block-group { min-height: 180px; display: flex; align-items: flex-end; border-radius: 8px; }
.exito-logos .exito-logo { font-weight: 700; font-size: 18px; opacity: .7; margin: 0 20px; }
.wp-block-details { border-bottom: 1px solid #e5e7eb; padding: 18px 0; }
.wp-block-details summary { font-weight: 700; cursor: pointer; }
.exito-monogram { display: flex; align-items: center; justify-content: center; aspect-ratio: 1 / 1; background: #F6F4F0; font-size: 42px; font-weight: 700; border-radius: 8px; }
.exito-role { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.exito-price { font-size: 1.6em; font-weight: 700; }
.exito-panel-number { font-size: 64px; }
.exito-listing-grid .wp-block-group.has-border-color { display: flex; flex-direction: column; height: 100%; border-radius: 16px !important; }
.exito-listing-grid .wp-block-image img { border-radius: 10px; }
.exito-listing-meta { font-size: 13px; opacity: .75; margin: 4px 0 6px; }
.exito-listing-grid h3 { font-size: 20px; margin: 0 0 8px; }
.exito-listing-text { font-size: 14px; opacity: .8; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.exito-listing-grid .exito-price { font-size: 1.25em; margin: auto 0 10px; padding-top: 12px; }
.exito-listing-grid .wp-block-button__link { background: transparent; color: inherit; box-shadow: inset 0 0 0 1.5px currentColor; }
@media (max-width: 781px) {
  .exito-alt-row { flex-direction: column; }
  .exito-stats-grid { flex-wrap: wrap !important; }
  .exito-stats-grid .wp-block-column { flex-basis: 45% !important; }
}
CSS;
    }

    private function safeHexColor(?string $color, string $fallback): string
    {
        $color = ltrim((string) $color, '#');
        if (strlen($color) === 6 && ctype_xdigit($color)) {
            return '#'.$color;
        }

        return $fallback;
    }
}
