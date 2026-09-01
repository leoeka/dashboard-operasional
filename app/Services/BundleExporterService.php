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
        $zip->addFromString('README.md', "# WordPress Install Bundle\n\n## Installation\n1. Extract this bundle.\n2. Upload and activate the ZIP inside `01-theme/`.\n3. Upload and activate the ZIP inside `02-plugin/`.\n4. Import the JSON files inside `03-elementor/` when Elementor is installed.\n5. Add the client content and assets from `04-content/` and `05-assets/`.\n\nThe outer ZIP is a delivery bundle. WordPress theme and plugin uploads use the nested ZIP files.\n");
        $zip->close();

        foreach ($temporaryArchives as $temporaryArchive) {
            @unlink($temporaryArchive);
        }

        return $themeInstallPath;
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
}
