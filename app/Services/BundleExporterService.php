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

        // theme-install.zip is the ONLY file that gets uploaded to WordPress:
        // the approved pages, their content, and every photo are already baked
        // into it (see injectPageImporterIntoTheme()) — install this one theme,
        // activate it, done. No separate plugin.
        //
        // It used to be wrapped in a second archive, bundle-export.zip, that
        // added a README plus text/JSON copies of the content and the client's
        // assets. Nothing outside the theme zip was ever uploaded or read, and
        // two files in the folder meant two chances to download the wrong one.
        // The README now ships inside the theme, where the installer sees it.
        $themeInstallPath = $outputDir.DIRECTORY_SEPARATOR.'theme-install.zip';
        $themeZip = new ZipArchive;
        if ($themeZip->open($themeInstallPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP theme WordPress tidak dapat dibuat.');
        }
        foreach ($themeFiles as $relativePath => $contents) {
            $themeZip->addFromString($relativePath, $contents);
        }
        $themeZip->close();

        return $themeInstallPath;
    }

    /**
     * Assembles the final theme file set: the deterministic shell
     * (style.css carrying the approved mockup stylesheet, the template files,
     * and the functions.php that loads it), a generated screenshot, the
     * client's real logo/photos, the deterministic page/photo importer
     * (injectPageImporterIntoTheme — this is what makes the theme
     * self-sufficient, no separate plugin), and a fresh style.css
     * cache-busting version.
     */
    private function buildCompleteThemeFiles(array $bundle, string $themeRoot): array
    {
        $themeFiles = $this->themeShell($bundle, $themeRoot);

        $themeFiles[$themeRoot.'/screenshot.png'] = $this->themeScreenshot($bundle);

        // The client's real logo/photos (BundleBuilderService::collectAssets),
        // written into the theme at the fixed paths the mockup markup
        // references, so the real files are always in the ZIP.
        $this->embedThemeAssets($bundle, $themeFiles, $themeRoot);

        // Render pages inside the same approved mockup shell and stylesheet.
        // Keep the markup in post_content as a core HTML block so it remains
        // available in the Block Editor while its frontend output stays exact.
        // The block holds the page only: the navigation bar and the footer come
        // from header.php/footer.php above, and their link list comes from the
        // WordPress menu the importer creates.
        // Deterministic (non-AI) page + photo importer, appended straight
        // into functions.php — see injectPageImporterIntoTheme()'s
        // docblock for why this lives in the theme instead of a plugin.
        $this->injectPageImporterIntoTheme($bundle, $themeFiles, $themeRoot);

        $this->bustThemeStyleVersion($themeFiles, $themeRoot);

        // Inside the theme folder, so the install guide arrives with the one
        // file the team actually uploads.
        $themeFiles[$themeRoot.'/README.md'] = $this->buildReadme($themeRoot);

        return $themeFiles;
    }

    /**
     * Every file of the shipped theme except the photos and the importer.
     *
     * There used to be a second shell here: GPT generated style.css,
     * header.php and footer.php, and the exporter then overwrote the parts of
     * it that had to match the approved mockup. Anything the model emitted
     * that this code did not think to replace stayed in the client's ZIP,
     * including PHP nobody had read. The mockup's own Blade views now render
     * the PNG, the live demo and the theme, so the shell is a fixed list of
     * files rather than a variable one.
     */
    private function themeShell(array $bundle, string $themeRoot): array
    {
        $css = trim((string) data_get($bundle, 'mockup_rendering.css', ''));
        if ($css === '') {
            // A theme with an empty stylesheet installs cleanly and renders an
            // unstyled site, which is the failure the client notices weeks
            // later. Name the stage that produced it instead.
            throw new \RuntimeException('Stylesheet mockup tidak ada di hasil build. Buat ulang WordPress dari opsi yang disetujui.');
        }

        $fontsUrl = (string) data_get($bundle, 'mockup_rendering.fonts_url', '');
        $language = data_get($bundle, 'mockup_rendering.lang') === 'en' ? 'en' : 'id';
        $cta = (string) data_get($bundle, 'mockup_rendering.cta', '');
        $chrome = data_get($bundle, 'mockup_rendering.chrome');
        if (! is_array($chrome) || ! isset($chrome['wrapper'], $chrome['header'], $chrome['footer'])) {
            // Without the chrome the installed site has no navigation bar and
            // no footer at all, which is the same class of silent failure as an
            // empty stylesheet — it installs cleanly and the client finds it.
            throw new \RuntimeException('Chrome mockup tidak ada di hasil build. Buat ulang WordPress dari opsi yang disetujui.');
        }

        // header.php and footer.php print this markup, so the navigation and the
        // footer belong to the theme instead of being repeated inside every
        // page's content. The link list itself is WordPress's own menu — the
        // importer creates it from the approved mockup (see
        // injectPageImporterIntoTheme) and the client edits it once under
        // Appearance > Menus. Same classes as the mockup's stylesheet expects.
        $navHelpers = <<<'PHP'
function exito_client_nav_menu(string $class = 'links'): void {
    wp_nav_menu([
        'theme_location' => 'primary',
        'container' => false,
        'menu_class' => $class,
        'items_wrap' => '<ul'.($class !== '' ? ' class="'.esc_attr($class).'"' : '').'>%3$s</ul>',
        'depth' => 1,
        'fallback_cb' => 'exito_client_pages_menu',
    ]);
}

// Before the import has run, or after someone deletes the menu, every
// published page is still a working link instead of an empty navigation bar.
function exito_client_pages_menu(array $args = []): void {
    $class = (string) ($args['menu_class'] ?? 'links');
    $items = wp_list_pages(['title_li' => '', 'depth' => 1, 'echo' => false]);

    echo '<ul'.($class !== '' ? ' class="'.esc_attr($class).'"' : '').'>'.$items.'</ul>';
}

// The mockup's call-to-action is a label with nowhere to go until the site has
// a contact page; then it points there.
function exito_client_cta_href(): string {
    foreach (['contact', 'kontak'] as $slug) {
        $page = get_page_by_path($slug);
        if ($page instanceof WP_Post && $page->post_status === 'publish') {
            return esc_url(get_permalink($page));
        }
    }

    return '#';
}

add_filter('wp_nav_menu_items', function ($items, $args) {
    $label = trim((string) $GLOBALS['exito_client_cta']);
    if ($label === '' || ($args->menu_class ?? '') !== 'links') {
        return $items;
    }

    return $items.'<li class="links-cta-item"><a class="links-cta" href="'.exito_client_cta_href().'">'.esc_html($label).'</a></li>';
}, 10, 2);

// WordPress marks the current page on the list item; this stylesheet marks the
// link, exactly as the approved mockup renders it.
add_filter('nav_menu_link_attributes', function ($atts, $item) {
    if (!empty($item->current)) {
        $atts['class'] = trim(($atts['class'] ?? '').' is-active');
    }

    return $atts;
}, 10, 2);
PHP;

        $template = <<<'PHP'
<?php get_header(); ?>
<?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
<?php get_footer(); ?>
PHP;

        return [
            // WordPress reads a theme's identity from a `Theme Name:` header
            // inside the first ~8KB of style.css (get_file_data()) — install
            // fails with a generic "invalid header" error without it.
            $themeRoot.'/style.css' => $this->themeHeader($themeRoot, $bundle)."\n\n".$css,
            $themeRoot.'/functions.php' => "<?php\nadd_action('after_setup_theme', function () { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('editor-styles'); add_editor_style('style.css'); register_nav_menus(['primary' => 'Menu utama']); });\n"
                // WordPress never loads style.css by itself. Without this the
                // theme installs, the pages exist, and the live site renders
                // them with no stylesheet at all. The version comes from the
                // header above, which bustThemeStyleVersion rewrites on every
                // build, so a rebuilt theme cannot serve a cached stylesheet.
                ."\nadd_action('wp_enqueue_scripts', function () { wp_enqueue_style('exito-client-theme', get_stylesheet_uri(), [], wp_get_theme()->get('Version')); });\n"
                ."\n\$GLOBALS['exito_mockup_fonts_url'] = ".var_export($fontsUrl, true).";\n"
                ."\n\$GLOBALS['exito_client_cta'] = ".var_export($cta, true).";\n"
                ."\n".$navHelpers."\n",
            $themeRoot.'/header.php' => str_replace(['__EXITO_LANGUAGE__', '__EXITO_CHROME__'], [$language, $chrome['wrapper']."\n".$chrome['header']], <<<'PHP'
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
__EXITO_CHROME__
PHP),
            $themeRoot.'/footer.php' => $chrome['footer']."\n</div>\n<?php wp_footer(); ?>\n</body>\n</html>\n",
            // The navigation and the footer come from the theme (above), so a
            // page the client creates later in WordPress gets the same chrome as
            // the approved pages; its content is only what the page itself adds.
            $themeRoot.'/page.php' => $template,
            $themeRoot.'/front-page.php' => $template,
            $themeRoot.'/index.php' => $template,
        ];
    }

    private function themeHeader(string $themeRoot, array $bundle): string
    {
        $name = str_replace('*/', '', trim((string) data_get($bundle, 'brand.company_name', '')));
        $name = $name !== '' ? $name.' — Exito Client Theme' : ucwords(str_replace('-', ' ', $themeRoot));

        return <<<CSS
/*
Theme Name: {$name}
Description: Approved client website, generated by Exito.
Version: 1.0.0
Requires at least: 5.9
Requires PHP: 7.4
*/
CSS;
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

    /**
     * Step-by-step install guide in Bahasa Indonesia, written for someone
     * installing this for the first time. It ships inside the theme folder of
     * the ZIP the team downloads, so the guide travels with the one file that
     * actually gets uploaded. There used to be a second "install & activate the
     * plugin" step here; that logic now lives inside the theme itself (see
     * injectPageImporterIntoTheme()). The old wrapper archive also carried
     * reference copies of the content and the client's assets, and this guide
     * described extracting it first; that archive is gone, so the install is
     * one upload with no extract step.
     *
     * Plain hyphens and ASCII punctuation only, on purpose: this text lands in
     * a ZIP that people open in editors and terminals with every kind of
     * encoding, and the previous copy arrived as mojibake.
     */
    private function buildReadme(string $themeRoot): string
    {
        return <<<MD
# Cara Install Website WordPress Ini

Cuma ada **1 file** yang perlu diupload ke WordPress: ZIP yang kamu download
dari dashboard (namanya `project-<id>-wordpress-theme.zip`). Isinya sudah
folder theme ini, jadi tidak perlu di-extract dulu.

## Langkah 1 - Pasang theme (satu-satunya langkah install)
1. Login ke **WordPress Admin** (`namadomain.com/wp-admin`).
2. Buka menu **Appearance > Themes**.
3. Klik **Add New Theme** (di bagian atas halaman), lalu **Upload Theme**.
4. Klik **Choose File**, pilih ZIP yang kamu download tadi.
5. Klik **Install Now**, tunggu sampai selesai, lalu klik **Activate**.

Tidak ada langkah plugin. Tidak ada langkah lain.

## Langkah 2 - Selesai, halaman sudah otomatis jadi
Begitu theme aktif, semua halaman (Home, About, Services, Contact, dst)
otomatis dibuat lengkap dengan isi teks dan foto, dan Home otomatis dijadikan
halaman depan website. Tidak ada langkah import manual, tidak perlu plugin
tambahan apa pun (termasuk tidak perlu Elementor).

Untuk memastikan:
- Buka menu **Pages** di WordPress Admin. Harus muncul halaman Home,
  About, Services, Contact (atau sesuai isi proposal), semuanya berstatus
  **Published**.
- Buka **Settings > Reading**. Pastikan "Your homepage displays" sudah otomatis
  terset ke **A static page**, dengan Homepage = **Home**.
- Buka menu **Appearance > Menus**. Menu bernama "Menu Utama" sudah dibuat dan
  sudah dipasang di lokasi "Menu utama" - isinya persis navigasi pada mockup.
- Kunjungi alamat website-nya langsung untuk lihat hasilnya.

Kalau setelah theme aktif halaman-halaman itu belum muncul, buka sembarang
halaman lain di WordPress Admin (misalnya klik menu Dashboard) lalu cek lagi
menu Pages - proses pembuatan halaman jalan otomatis begitu ada admin yang
membuka wp-admin setelah theme aktif. Ada juga notifikasi kuning di atas
wp-admin dengan tombol "Buat/perbarui halaman sekarang" kalau perlu dipicu
manual.

## Langkah 3 - Edit isi halaman
Untuk mengubah teks, foto, atau urutan section:
1. Buka menu **Pages**, klik halaman yang mau diedit (misalnya "Home").
2. Klik **Edit** - akan terbuka **Block Editor** bawaan WordPress.
3. Klik blok HTML untuk mengubah isi halaman. Markup di dalamnya mengikuti
   mockup yang dipilih klien. Perubahan urutan section atau tampilannya perlu
   dilakukan pada markup tersebut.
4. Setelah selesai, klik **Update** (atau **Publish**) di kanan atas.

Susunan visual halaman mengikuti mockup yang dipilih klien. Tidak perlu plugin
tambahan untuk mengubah isinya.

## Langkah 4 - Menambah halaman atau mengubah menu
Menu atas dan daftar tautan di footer bukan tulisan tempelan di dalam halaman,
melainkan **Menu Utama** biasa milik WordPress. Jadi:

1. Buat halamannya lebih dulu (menu **Pages > Add New**), lalu Publish.
2. Buka **Appearance > Menus**, pilih "Menu Utama", centang halaman baru di
   kolom kiri, atur urutannya dengan drag, klik **Save Menu**.
3. Semua halaman langsung ikut menampilkan menu yang baru - tidak perlu
   menyunting satu per satu.

Navigasi dan footer juga otomatis menempel pada halaman baru itu, karena mereka
dipasang oleh theme, bukan disimpan di dalam tiap halaman.

## Kalau ada masalah
- **Upload theme gagal "The uploaded file exceeds..."** - biasanya batas ukuran
  upload hosting terlalu kecil. Hubungi provider hosting untuk menaikkan
  `upload_max_filesize` dan `post_max_size`, atau upload manual lewat
  FTP/File Manager: extract ZIP-nya, upload folder `{$themeRoot}` hasil extract
  ke `wp-content/themes/` di server, lalu aktifkan lewat menu
  **Appearance > Themes** seperti biasa.
- **Halaman kosong atau foto tidak muncul** - buka halamannya di Block Editor.
  Foto biasanya butuh beberapa detik untuk ter-upload otomatis saat theme
  pertama kali aktif; refresh halaman **Pages** sekali lagi, atau pakai tombol
  "Buat/perbarui halaman sekarang" di notifikasi admin.
- **Tampilan website beda dari mockup** - pastikan theme yang aktif adalah
  `{$themeRoot}`, bukan theme bawaan WordPress.
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
        // The client's real logo ships inside the theme and the theme's own
        // header points at it, so the brand mark is there the moment the theme
        // is active — this does not wait on the page import below.
        $imagesForExport = [];
        $logo = $bundle['assets']['logo'] ?? null;
        if (is_array($logo) && ! empty($logo['filename']) && is_string($logo['bytes'] ?? null) && $logo['bytes'] !== '') {
            $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '-', $logo['filename']) ?: 'client-logo.png';
            $themeFiles[$themeRoot.'/assets/'.$safeFilename] = $logo['bytes'];
            $imagesForExport[$safeFilename] = 'assets/'.$safeFilename;

            $logoMarkup = "<?php echo esc_url(get_stylesheet_directory_uri().'/assets/{$safeFilename}'); ?>";
            foreach (['header.php', 'footer.php'] as $chromeFile) {
                $key = $themeRoot.'/'.$chromeFile;
                $themeFiles[$key] = str_replace('__EXITO_THEME_ASSET__', $logoMarkup, $themeFiles[$key]);
            }
        }

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

        $exportedPages = var_export($pagesForExport, true);
        $exportedImages = var_export($imagesForExport, true);
        $exportedNav = var_export(array_values((array) data_get($bundle, 'mockup_rendering.nav', [])), true);
        // Identifies THIS build's content — changes any time the approved
        // mockup/photos are rebuilt. Lets the importer below tell "never
        // imported yet" apart from "imported an OLDER version of this
        // content", so re-uploading a rebuilt theme (e.g. a fixed layout)
        // actually refreshes the live pages instead of leaving whatever an
        // earlier build already created untouched forever.
        $contentVersion = var_export(md5($exportedPages.$exportedImages.$exportedNav), true);
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

// The navigation the client approved: label + the page slug it points at. The
// page ids only exist once the pages have been created, so this becomes the
// menu's items inside exito_client_import_menu().
function exito_client_nav_items() {
    return {$exportedNav};
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

    exito_client_import_menu(\$page_ids);

    if (\$created > 0) {
        update_option('exito_client_pages_imported', exito_client_content_version());
    }

    return \$created;
}

// The approved navigation becomes a real WordPress menu: the client adds a page
// or renames a link once under Appearance > Menus and every page follows,
// instead of editing the HTML of each page. Re-running the import replaces this
// theme's menu items rather than stacking up duplicates.
function exito_client_import_menu(array \$page_ids): void {
    \$items = exito_client_nav_items();
    if (!\$items || !\$page_ids) {
        return;
    }

    if (!function_exists('wp_create_nav_menu')) {
        require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
    }
    if (!function_exists('wp_create_nav_menu')) {
        return;
    }

    \$name = 'Menu Utama';
    \$menu_id = (int) get_option('exito_client_nav_menu_id');
    if (!\$menu_id || !wp_get_nav_menu_object(\$menu_id)) {
        \$existing = wp_get_nav_menu_object(\$name);
        \$menu_id = \$existing ? (int) \$existing->term_id : (int) wp_create_nav_menu(\$name);
        if (!\$menu_id || is_wp_error(\$menu_id)) {
            return;
        }
    } else {
        foreach ((array) wp_get_nav_menu_items(\$menu_id) as \$stale) {
            wp_delete_post((int) \$stale->ID, true);
        }
    }

    update_option('exito_client_nav_menu_id', \$menu_id);

    foreach (\$items as \$item) {
        \$page_id = (int) (\$page_ids[\$item['slug']] ?? 0);
        if (!\$page_id) {
            continue;
        }

        wp_update_nav_menu_item(\$menu_id, 0, [
            'menu-item-title' => (string) \$item['label'],
            'menu-item-type' => 'post_type',
            'menu-item-object' => 'page',
            'menu-item-object-id' => \$page_id,
            'menu-item-status' => 'publish',
        ]);
    }

    \$locations = get_theme_mod('nav_menu_locations', []);
    \$locations['primary'] = \$menu_id;
    set_theme_mod('nav_menu_locations', \$locations);
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
     * Rewrites style.css's `Version:` header to a fresh timestamp on every
     * build. functions.php enqueues style.css with
     * `wp_get_theme()->get('Version')` as its cache-busting query string
     * (`style.css?ver=...`), and the header themeShell() writes always says
     * "1.0.0" — without this, re-uploading a rebuilt theme with real CSS
     * changes could still show a browser or proxy cached copy of an EARLIER
     * build's stylesheet, since the URL never changes.
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

    /**
     * Writes the client's real logo/photos (BundleBuilderService::
     * collectAssets()) into the theme at `assets/<filename>` — the same
     * filenames the mockup rendering was given as image tokens, so the bytes
     * and the markup that points at them are written by this one build.
     */
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
}
