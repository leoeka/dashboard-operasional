<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\WpBuild;
use App\Support\MockupWorkspace;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pembangun tema WordPress dari artefak kandidat mockup yang dipilih klien.
 *
 * Menerapkan kontrak penahan rancangan builder 2026-10-21:
 * 1. Tema lahir dari kandidat yang sama (artefak index.html yang disetujui klien).
 * 2. Seluruh penanda kontak (__EXITO_PAGE_URL:telepon|email|whatsapp) diganti
 *    dengan data nyata klien CRM saat build.
 * 3. Seluruh penanda gambar (__EXITO_IMAGE:slot) diarahkan ke folder images/ tema.
 * 4. Berkas tema terstruktur standar WordPress (style.css, functions.php, header.php,
 *    footer.php, front-page.php, index.php, screenshot.png).
 * 5. Nol penanda __EXITO_ tersisa di seluruh berkas tema keluaran.
 */
class WpThemeBuilder
{
    public function __construct(
        private ?MockupWorkspace $workspace = null,
    ) {
        $this->workspace = $this->workspace ?? new MockupWorkspace();
    }

    public function build(WpBuild $build): WpBuild
    {
        $candidate = $build->candidate;

        if (!$candidate) {
            throw new RuntimeException('WpBuild #' . $build->id . ' tidak memiliki kandidat mockup.');
        }

        $candidateDir = $this->workspace->resolve((string) $candidate->folder);
        $indexHtmlPath = $candidateDir . '/index.html';

        if (!is_file($indexHtmlPath)) {
            throw new RuntimeException('Berkas index.html tidak ditemukan pada folder kandidat: ' . $candidate->folder);
        }

        $html = file_get_contents($indexHtmlPath);

        if ($html === false || trim($html) === '') {
            throw new RuntimeException('Berkas index.html pada kandidat kosong atau tidak dapat dibaca.');
        }

        $project = $build->project;
        $client = $project->client;

        // Siapkan folder tujuan tema
        $themeDir = $this->workspace->buildPath($build);
        $imagesDir = $themeDir . '/images';

        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0775, true);
        }

        // 1. Salin aset gambar dan screenshot dari folder kandidat
        $this->copyCandidateAssets($candidateDir, $themeDir, $imagesDir);

        // 2. Ganti seluruh penanda kontak dan gambar
        $processedHtml = $this->replaceMarkers($html, $client);

        // 3. Pisahkan CSS dan tulis style.css dengan header tema WordPress yang sah
        $this->writeStyleCss($processedHtml, $themeDir, $project);

        // 4. Tulis functions.php
        $this->writeFunctionsPhp($themeDir, $project);

        // 5. Tulis header.php, footer.php, front-page.php, dan index.php
        $this->writeTemplateFiles($processedHtml, $themeDir);

        // 6. Validasi integritas: pastikan nol penanda __EXITO_ tersisa
        $this->assertNoRemainingMarkers($themeDir);

        // 7. Perbarui status build ke inspection (siap untuk Tahap 9 Meja Periksa)
        $build->update([
            'folder' => $this->workspace->relativeBuildFolder($build),
            'status' => WpBuild::STATUS_INSPECTION,
        ]);

        return $build;
    }

    /**
     * Mengganti seluruh penanda CTA kontak dan gambar dengan data nyata klien.
     */
    public function replaceMarkers(string $html, ?Client $client): string
    {
        $phone = trim((string) ($client?->phone ?: $client?->whatsapp ?: ''));
        $email = trim((string) ($client?->email ?: $client?->billingEmail() ?: ''));
        $whatsapp = trim((string) ($client?->whatsapp ?: $client?->phone ?: ''));

        // Format nomor telepon
        $cleanPhone = preg_replace('/[^\+0-9]/', '', $phone);
        $phoneUrl = filled($cleanPhone) ? 'tel:' . $cleanPhone : '#';

        // Format email
        $cleanEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $emailUrl = filled($cleanEmail) ? 'mailto:' . $cleanEmail : '#';

        // Format tautan WhatsApp (standar internasional tanpa +, diawali 62 untuk nomor Indonesia)
        $waDigits = preg_replace('/[^0-9]/', '', $whatsapp);
        if (str_starts_with($waDigits, '0')) {
            $waDigits = '62' . substr($waDigits, 1);
        }
        $whatsappUrl = filled($waDigits) ? 'https://wa.me/' . $waDigits : '#';

        // Ganti penanda kontak spesifik
        $replacements = [
            '__EXITO_PAGE_URL:telepon' => $phoneUrl,
            '__EXITO_PAGE_URL:email' => $emailUrl,
            '__EXITO_PAGE_URL:whatsapp' => $whatsappUrl,
        ];

        $result = strtr($html, $replacements);

        // Ganti penanda internal page URL (__EXITO_PAGE_URL:kontak, __EXITO_PAGE_URL:tentang, dll)
        $result = preg_replace_callback(
            '/__EXITO_PAGE_URL:([a-zA-Z0-9_-]+)/',
            function (array $matches): string {
                $slug = strtolower($matches[1]);
                if (in_array($slug, ['kontak', 'contact', 'telepon', 'email', 'whatsapp'])) {
                    return '#kontak';
                }
                return '<?php echo esc_url(home_url(\'/' . $slug . '/\')); ?>';
            },
            $result
        );

        // Ganti penanda gambar (__EXITO_IMAGE:slot atau __EXITO_IMAGE:slot.jpg)
        $result = preg_replace_callback(
            '/__EXITO_IMAGE:([a-zA-Z0-9_\.-]+)/',
            function (array $matches): string {
                $filename = $matches[1];
                // Jika slot tidak memiliki ekstensi, default ke .jpg
                if (!str_contains($filename, '.')) {
                    $filename .= '.jpg';
                }
                return '<?php echo esc_url(get_template_directory_uri() . \'/images/' . $filename . '\'); ?>';
            },
            $result
        );

        return (string) $result;
    }

    private function copyCandidateAssets(string $candidateDir, string $themeDir, string $imagesDir): void
    {
        // Salin preview.png menjadi screenshot.png untuk WordPress theme preview
        $previewPath = $candidateDir . '/preview.png';
        if (is_file($previewPath)) {
            copy($previewPath, $themeDir . '/screenshot.png');
        }

        // Salin berkas gambar di folder kandidat ke images/
        $files = glob($candidateDir . '/*.{jpg,jpeg,png,webp,svg,gif}', GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            $basename = basename($file);
            if ($basename === 'preview.png') {
                continue;
            }
            copy($file, $imagesDir . '/' . $basename);
        }
    }

    private function writeStyleCss(string &$html, string $themeDir, Project $project): void
    {
        $extractedCss = '';

        // Ambil semua isi di dalam tag <style>...</style>
        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches)) {
            foreach ($matches[1] as $cssBlock) {
                $extractedCss .= "\n" . trim($cssBlock);
            }
            // Hapus tag style dari markah HTML agar tidak duplikat
            $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        }

        // style.css dilayani sebagai teks statis oleh web server dan tidak dieksekusi PHP.
        // Konversi penanda gambar PHP di dalam CSS menjadi jalur relatif images/ yang valid.
        $extractedCss = (string) preg_replace(
            '/<\?php\s+echo\s+esc_url\(get_template_directory_uri\(\)\s*\.\s*\'\/images\/(.*?)\'\);\s*\?>/i',
            'images/$1',
            $extractedCss
        );

        $themeName = str_replace(['*/', "\n", "\r"], '', $project->name);
        $clientName = str_replace(['*/', "\n", "\r"], '', (string) ($project->client_name ?: $project->name));
        $textDomain = Str::slug($project->name) ?: 'siteflow-theme';

        $header = <<<CSS
/*
Theme Name: {$themeName}
Theme URI: https://siteflow.id
Author: SiteFlow
Author URI: https://siteflow.id
Description: Tema WordPress khusus untuk {$clientName}.
Version: 1.0.0
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Text Domain: {$textDomain}
*/

CSS;

        $fullCss = $header . "\n" . trim($extractedCss) . "\n";
        file_put_contents($themeDir . '/style.css', $fullCss);
    }

    private function writeFunctionsPhp(string $themeDir, Project $project): void
    {
        $safeName = addslashes($project->name);

        $content = <<<PHP
<?php
/**
 * Konfigurasi tema WordPress untuk {$safeName}.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('editor-styles');
    add_editor_style('style.css');
    register_nav_menus([
        'primary' => 'Menu Utama',
        'footer'  => 'Menu Footer',
    ]);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'siteflow-theme-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );
});

PHP;

        file_put_contents($themeDir . '/functions.php', $content);
    }

    private function writeTemplateFiles(string $html, string $themeDir): void
    {
        // Pisahkan bagian header, main content, dan footer
        $headerMarkup = '';
        $footerMarkup = '';
        $mainMarkup = $html;

        // Ambil isi <body> jika ada
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $bodyMatches)) {
            $mainMarkup = $bodyMatches[1];
        }

        // Cari <header>...</header>
        if (preg_match('/<header\b[^>]*>.*?<\/header>/is', $mainMarkup, $headerMatches)) {
            $headerMarkup = $headerMatches[0];
            $mainMarkup = str_replace($headerMarkup, '', $mainMarkup);
        }

        // Cari <footer>...</footer>
        if (preg_match('/<footer\b[^>]*>.*?<\/footer>/is', $mainMarkup, $footerMatches)) {
            $footerMarkup = $footerMatches[0];
            $mainMarkup = str_replace($footerMarkup, '', $mainMarkup);
        }

        // 1. Tulis header.php
        $headerFile = <<<PHP
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
{$headerMarkup}

PHP;
        file_put_contents($themeDir . '/header.php', trim($headerFile) . "\n");

        // 2. Tulis footer.php
        $footerFile = <<<PHP
{$footerMarkup}
<?php wp_footer(); ?>
</body>
</html>

PHP;
        file_put_contents($themeDir . '/footer.php', trim($footerFile) . "\n");

        // 3. Tulis front-page.php
        $frontPageFile = <<<PHP
<?php get_header(); ?>

{$mainMarkup}

<?php get_footer(); ?>

PHP;
        file_put_contents($themeDir . '/front-page.php', trim($frontPageFile) . "\n");

        // 4. Tulis index.php (fallback template standar WordPress)
        $indexFile = <<<PHP
<?php
get_header();

if (have_posts()) :
    while (have_posts()) : the_post();
        the_content();
    endwhile;
endif;

get_footer();

PHP;
        file_put_contents($themeDir . '/index.php', trim($indexFile) . "\n");
    }

    /**
     * Memastikan tidak ada satu pun penanda __EXITO_ yang tertinggal di seluruh berkas tema.
     */
    private function assertNoRemainingMarkers(string $themeDir): void
    {
        $phpFiles = glob($themeDir . '/*.php') ?: [];
        $cssFiles = glob($themeDir . '/*.css') ?: [];
        $allFiles = array_merge($phpFiles, $cssFiles);

        foreach ($allFiles as $filePath) {
            $content = (string) file_get_contents($filePath);
            if (str_contains($content, '__EXITO_')) {
                throw new RuntimeException(
                    'Masih terdapat penanda __EXITO_ yang belum terganti pada berkas: ' . basename($filePath)
                );
            }
        }
    }
}
