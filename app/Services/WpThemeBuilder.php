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
 * Menerapkan kontrak rancangan builder 2026-10-21 dan rancangan bentuk tema
 * 2026-10-30:
 * 1. Tema lahir dari kandidat yang sama (artefak index.html + pages/*.html).
 * 2. Penanda kontak diganti data nyata klien CRM saat build.
 * 3. Foto slot menjadi images/ tema; halaman isi memakai token yang diganti
 *    importer saat tema aktif.
 * 4. Tautan internal menjadi halaman WordPress sungguhan lewat importer yang
 *    ditanam di functions.php (menu, pustaka media, halaman).
 * 5. Nol penanda __EXITO_ tersisa di seluruh berkas tema.
 */
class WpThemeBuilder
{
    private const CONTENT_TOKEN_PATTERN = '/\[\[siteflow:([a-z]+):([^\]]+)\]\]/';

    private const HOME_SLUG = 'beranda';

    private const HOME_ALIASES = ['home', 'beranda', 'index'];

    /** Rasio foto per slot (rancangan foto 2026-11-02). */
    private const PHOTO_RATIOS = ['hero' => '4 / 3', 'galeri-1' => '1 / 1'];

    private const PHOTO_SCOPES = [
        '.siteflow-page.siteflow-page',
        '.elementor-edit-area.elementor-edit-area',
    ];

    /** Tag yang boleh menjadi bingkai foto; selain ini dibungkus figure. */
    private const PHOTO_CONTAINERS = ['div', 'figure'];

    private const VOID_TAGS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link',
        'meta', 'param', 'source', 'track', 'wbr',
    ];

    public function __construct(
        private ?MockupWorkspace $workspace = null,
        private ?ElementorPageMapper $elementor = null,
        private ?ElementorCssBridge $cssBridge = null,
    ) {
        $this->workspace = $this->workspace ?? new MockupWorkspace();
        $this->elementor = $this->elementor ?? new ElementorPageMapper();
        $this->cssBridge = $this->cssBridge ?? new ElementorCssBridge();
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

        $html = $this->stripKnownMenuScript($html);

        $project = $build->project;
        $client = $project->client;
        $imageFiles = $this->imageFileIndex($candidateDir);
        $contentPages = $this->readContentPages($candidateDir);
        $contentSlugs = array_keys($contentPages);
        $navItems = $this->navItems($html, $contentSlugs);

        $themeDir = $this->workspace->buildPath($build);
        $this->clearGeneratedAssets($themeDir);
        $imagesDir = $themeDir . '/images';

        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0775, true);
        }

        $this->copyCandidateAssets($candidateDir, $themeDir, $imagesDir);

        [$frontSource, $frontCssBlocks] = $this->extractStyles($html);

        $frontHtml = $this->replaceThemeMarkers($frontSource, $client, $imageFiles, $contentSlugs);
        $frontHtml = $this->convertHeaderNavToMenu($frontHtml);

        $pageMarkup = [
            self::HOME_SLUG => $this->tagPhotoContainers($this->unwrapMainWrapper($this->replaceContentMarkers(
                $this->stripChrome($this->bodyInner($frontSource)),
                $contentSlugs,
                $client,
                $imageFiles
            ))),
        ];
        $pageTitles = [self::HOME_SLUG => $this->pageTitle($frontSource, 'Beranda')];

        foreach ($contentPages as $slug => $page) {
            $pageMarkup[$slug] = $this->wrapPageScope($slug, $this->tagPhotoContainers($this->unwrapMainWrapper(
                $this->replaceContentMarkers($page['html'], $contentSlugs, $client, $imageFiles)
            )));
            $pageTitles[$slug] = $page['title'];
        }

        $frontCss = $this->joinCssBlocks($frontCssBlocks);

        if ($this->hasStaticMobileMenu($html)) {
            $frontCss .= "\n@media(max-width:800px){.menu-toggle{display:none!important}.navlinks{display:flex!important}}\n";
        }

        $contentCss = $this->scopeContentCss($contentPages, $pageMarkup);
        $flexClasses = $this->cssBridge->displayContainerClasses($frontCss . "\n" . $contentCss);

        $elementorData = [];
        $buttonClasses = [];
        $imageClasses = [];
        $headingClasses = [];
        $imageAlts = [];

        foreach ($pageMarkup as $slug => $markup) {
            $compiled = $this->elementor->compile($markup, $slug, $flexClasses);
            $elementorData[$slug] = $compiled['elements'];
            $buttonClasses = array_merge($buttonClasses, $compiled['buttons']);
            $imageClasses = array_merge($imageClasses, $compiled['image_classes']);
            $headingClasses = array_merge($headingClasses, $compiled['heading_classes']);
            $imageAlts = array_merge($imageAlts, $compiled['image_alts']);
        }

        $this->writeStyleCss($frontCss, $contentCss, $themeDir, $project, $imageFiles, $buttonClasses, $imageClasses, $headingClasses);
        $this->writeFunctionsPhp($themeDir, $project);
        $this->writeTemplateFiles($frontHtml, $themeDir);
        $this->writeContent($themeDir, $project, $pageMarkup, $pageTitles, $elementorData, $navItems, $imageFiles, $imageAlts);
        $this->assertNoRemainingMarkers($themeDir);

        $build->update([
            'folder' => $this->workspace->relativeBuildFolder($build),
            'status' => WpBuild::STATUS_INSPECTION,
        ]);

        return $build;
    }

    /**
     * Penanda untuk berkas templat tema (PHP dieksekusi WordPress).
     *
     * @param array<string, string> $imageFiles peta slot -> nama berkas nyata
     * @param list<string> $pageSlugs halaman isi yang akan dibuat importer
     */
    public function replaceMarkers(string $html, ?Client $client, array $imageFiles = [], array $pageSlugs = []): string
    {
        return $this->replaceThemeMarkers($html, $client, $imageFiles, $pageSlugs);
    }

    /**
     * Mengganti penanda CTA kontak dengan data nyata klien. Tautan tanpa data
     * dibuang utuh (K-7), bukan dibiarkan menunjuk '#'.
     */
    private function replaceContactMarkers(string $html, ?Client $client): string
    {
        $phone = (string) preg_replace('/[^+0-9]/', '', (string) ($client?->phone ?: $client?->whatsapp ?: ''));
        $email = trim((string) ($client?->email ?: $client?->billingEmail() ?: ''));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $whatsapp = $this->whatsappDigits((string) ($client?->whatsapp ?: $client?->phone ?: ''));

        $contacts = [
            '__EXITO_PAGE_URL:telepon' => $phone !== '' ? 'tel:' . $phone : null,
            '__EXITO_PAGE_URL:email' => $email !== '' ? 'mailto:' . $email : null,
            '__EXITO_PAGE_URL:whatsapp' => $whatsapp !== '' ? 'https://wa.me/' . $whatsapp : null,
        ];

        $result = $html;

        foreach ($contacts as $marker => $url) {
            if ($url !== null) {
                $result = $this->replaceExactHref($result, $marker, $url);
            } else {
                $result = $this->unwrapLinks($result, $marker);
            }

            if ($this->hasMarker($result, $marker)) {
                throw new RuntimeException('Penanda ' . $marker . ' dipakai di luar tautan; target tanpa data tidak boleh dirender (K-7).');
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $imageFiles
     * @param list<string> $pageSlugs
     */
    private function replaceThemeMarkers(string $html, ?Client $client, array $imageFiles, array $pageSlugs): string
    {
        $result = $this->replaceContactMarkers($html, $client);
        $result = $this->replaceInternalLinks($result, $pageSlugs, 'theme');
        $result = $this->replaceRelativeLinks($result, $pageSlugs, 'theme');

        return $this->replaceImageMarkers($result, $imageFiles, 'theme');
    }

    /**
     * @param list<string> $pageSlugs
     * @param array<string, string> $imageFiles
     */
    private function replaceContentMarkers(string $html, array $pageSlugs, ?Client $client, array $imageFiles): string
    {
        $result = $this->replaceContactMarkers($html, $client);
        $result = $this->replaceInternalLinks($result, $pageSlugs, 'content');
        $result = $this->replaceRelativeLinks($result, $pageSlugs, 'content');

        return $this->replaceImageMarkers($result, $imageFiles, 'content');
    }

    /**
     * Tautan internal yang punya halaman isi menjadi tautan nyata; yang tidak
     * punya halaman dibuang (K-7). Tema memakai helper PHP, isi halaman
     * memakai token karena basis data WordPress tidak menjalankan PHP.
     *
     * @param list<string> $pageSlugs
     */
    private function replaceInternalLinks(string $html, array $pageSlugs, string $mode): string
    {
        preg_match_all('/__EXITO_PAGE_URL:([a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9]+)?)/', $html, $found);

        $result = $html;

        foreach (array_unique($found[1]) as $slugRaw) {
            $marker = '__EXITO_PAGE_URL:' . $slugRaw;

            if (!str_contains($result, $marker)) {
                continue;
            }

            $slug = strtolower((string) preg_replace('/\.[a-zA-Z0-9]+$/', '', $slugRaw));

            if (in_array($slug, self::HOME_ALIASES, true)) {
                $slug = self::HOME_SLUG;
            }

            if ($slug === self::HOME_SLUG || in_array($slug, $pageSlugs, true)) {
                $href = $mode === 'theme'
                    ? "<?php echo esc_url(siteflow_page_url('{$slug}')); ?>"
                    : '[[siteflow:halaman:' . $slug . ']]';
                $result = $this->replaceExactHref($result, $marker, $href);
            } elseif (in_array($slug, ['kontak', 'contact'], true) && preg_match('/(?<![-\w])id\s*=\s*["\']' . $slug . '["\']/', $result)) {
                $result = $this->replaceExactHref($result, $marker, '#' . $slug);
            }

            // Halaman tujuan tidak ada: tautannya dibuang (K-7, tanpa 404).
            $result = $this->unwrapLinks($result, $marker);

            if ($this->hasMarker($result, $marker)) {
                throw new RuntimeException('Penanda ' . $marker . ' menunjuk halaman yang tidak dibuat; tautannya tidak boleh dirender (K-7).');
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $imageFiles
     */
    private function replaceImageMarkers(string $html, array $imageFiles, string $mode): string
    {
        return (string) preg_replace_callback(
            '/__EXITO_IMAGE:([a-zA-Z0-9_.-]+)/',
            function (array $matches) use ($imageFiles, $mode): string {
                $filename = $this->resolveImageFile($matches[1], $imageFiles);

                if ($mode === 'content') {
                    return '[[siteflow:gambar:' . $filename . ']]';
                }

                return '<?php echo esc_url(get_template_directory_uri() . \'/images/' . $filename . '\'); ?>';
            },
            $html
        );
    }

    /**
     * @param array<string, string> $imageFiles
     */
    private function resolveImageFile(string $slot, array $imageFiles): string
    {
        $base = strtolower((string) preg_replace('/\.(jpe?g|png|webp|gif|svg)$/i', '', $slot));

        if (!isset($imageFiles[$base])) {
            throw new RuntimeException('Slot gambar \'' . $base . '\' tidak punya berkas di folder kandidat. Unduh dulu lewat unduh_gambar.');
        }

        return $imageFiles[$base];
    }

    /**
     * @return array<string, string>
     */
    private function imageFileIndex(string $candidateDir): array
    {
        $index = [];

        foreach (glob($candidateDir . '/*.{jpg,jpeg,png,webp,gif,svg}', GLOB_BRACE) ?: [] as $file) {
            $basename = basename($file);

            if ($basename === 'preview.png') {
                continue;
            }

            if (strtolower(pathinfo($basename, PATHINFO_EXTENSION)) === 'svg') {
                throw new RuntimeException("Gambar '{$basename}' berformat .svg. WordPress menolak SVG secara bawaan dan SVG bisa membawa skrip; pakai JPG, PNG, atau WebP.");
            }

            $index[strtolower(pathinfo($basename, PATHINFO_FILENAME))] = $basename;
        }

        return $index;
    }

    /**
     * Membaca berkas pages/<slug>.html kandidat: judul, CSS, dan isi badan
     * tanpa chrome global (header/footer milik tema).
     *
     * @return array<string, array{title: string, html: string, css: list<string>}>
     */
    private function readContentPages(string $candidateDir): array
    {
        $pages = [];

        foreach (glob($candidateDir . '/pages/*.html') ?: [] as $file) {
            $slug = strtolower(pathinfo($file, PATHINFO_FILENAME));

            if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
                continue;
            }

            // Beranda dibangun dari isi index.html. Salinan pages/home.html
            // hasil kandidat bukan halaman kedua.
            if (in_array($slug, self::HOME_ALIASES, true)) {
                continue;
            }

            $raw = file_get_contents($file);

            if ($raw === false || trim($raw) === '') {
                continue;
            }

            $raw = $this->stripKnownMenuScript($raw);

            $title = ucfirst(str_replace('-', ' ', $slug));

            if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $raw, $titleMatch)) {
                $candidateTitle = trim((string) preg_replace('/\s+/', ' ', strip_tags($titleMatch[1])));

                if ($candidateTitle !== '') {
                    $title = $candidateTitle;
                }
            }

            [$withoutStyles, $cssBlocks] = $this->extractStyles($raw);
            $body = $this->unwrapMainWrapper($this->stripChrome($this->bodyInner($withoutStyles)));

            if (trim(strip_tags($body)) === '') {
                throw new RuntimeException('Halaman isi \'' . $slug . '\' kosong setelah chrome dibuang; berkas pages/' . $slug . '.html tidak berguna.');
            }

            $pages[$slug] = [
                'title' => $title,
                'html' => $body,
                'css' => $cssBlocks,
            ];
        }

        return $pages;
    }

    /**
     * Label menu diambil dari teks tautan di index.html. Tautan di dalam
     * <nav> didahulukan (itu navigasi halaman); tautan lain seperti logo
     * hanya jadi cadangan. Hanya slug yang punya berkas halaman masuk menu.
     * Dua bentuk tautan diterima: penanda jalur API dan tautan relatif
     * `pages/<slug>.html` hasil jalur Codex.
     *
     * @param list<string> $pageSlugs
     * @return list<array{slug: string, label: string}>
     */
    private function navItems(string $html, array $pageSlugs): array
    {
        preg_match_all('/<nav\b[^>]*>.*?<\/nav>/is', $html, $navBlocks, PREG_OFFSET_CAPTURE);

        $navRanges = [];

        foreach ($navBlocks[0] as $block) {
            $navRanges[] = [$block[1], $block[1] + strlen((string) $block[0])];
        }

        preg_match_all(
            '/<a\b[^>]*(?<![-\w:])href\s*=\s*(["\'])([^"\']+)\1[^>]*>(.*?)<\/a>/is',
            $html,
            $links,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        $fromNav = [];
        $fallback = [];

        foreach ($links as $link) {
            $slug = $this->pageSlugFromHref((string) $link[2][0], $pageSlugs);

            if ($slug === null) {
                continue;
            }

            $label = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $link[3][0])));
            $entry = [
                'slug' => $slug,
                'label' => $label !== '' ? $label : ucfirst(str_replace('-', ' ', $slug)),
            ];

            $offset = (int) $link[0][1];
            $insideNav = false;

            foreach ($navRanges as [$start, $end]) {
                if ($offset >= $start && $offset < $end) {
                    $insideNav = true;
                    break;
                }
            }

            if ($insideNav) {
                $fromNav[$slug] = $fromNav[$slug] ?? $entry;
            } else {
                $fallback[$slug] = $fallback[$slug] ?? $entry;
            }
        }

        return array_values($fromNav + $fallback);
    }

    /**
     * Tautan halaman relatif (`pages/tentang.html`, `produk.html`,
     * `../index.html`) dijadikan tautan WordPress yang sama dengan penanda.
     * Kandidat jalur Codex menulis tautan seperti ini, bukan penanda.
     *
     * @param list<string> $pageSlugs
     */
    private function replaceRelativeLinks(string $html, array $pageSlugs, string $mode): string
    {
        return (string) preg_replace_callback(
            '/<a\b([^>]*?)(?<![-\w:])href\s*=\s*(["\'])([^"\']*)\2([^>]*)>(.*?)<\/a>/is',
            function (array $match) use ($pageSlugs, $mode): string {
                $slug = $this->pageSlugFromHref($match[3], $pageSlugs);

                if ($slug !== null) {
                    $target = $mode === 'theme'
                        ? "<?php echo esc_url(siteflow_page_url('{$slug}')); ?>"
                        : '[[siteflow:halaman:' . $slug . ']]';

                    return '<a' . $match[1] . 'href="' . $target . '"' . $match[4] . '>' . $match[5] . '</a>';
                }

                if (!$this->isHomeHref($match[3])) {
                    return $match[0];
                }

                if ($mode === 'theme') {
                    return '<a' . $match[1] . 'href="<?php echo esc_url(home_url(\'/\')); ?>"' . $match[4] . '>' . $match[5] . '</a>';
                }

                // Beranda kini halaman WordPress sungguhan: token yang sama
                // dengan tautan antar-halaman lain, diganti importer.
                return '<a' . $match[1] . 'href="[[siteflow:halaman:' . self::HOME_SLUG . ']]"' . $match[4] . '>' . $match[5] . '</a>';
            },
            $html
        );
    }

    /**
     * @param list<string> $pageSlugs
     */
    private function pageSlugFromHref(string $href, array $pageSlugs): ?string
    {
        $href = trim($href);

        if (str_starts_with($href, '__EXITO_PAGE_URL:')) {
            $slug = strtolower((string) preg_replace('/\.[a-zA-Z0-9]+$/', '', substr($href, strlen('__EXITO_PAGE_URL:'))));

            return in_array($slug, $pageSlugs, true) ? $slug : null;
        }

        $slug = $this->pathSlug($href);

        return $slug !== null && in_array($slug, $pageSlugs, true) ? $slug : null;
    }

    private function isHomeHref(string $href): bool
    {
        $path = $this->pathSlug($href);

        return in_array($path, ['index', 'home', 'beranda'], true);
    }

    /**
     * Nama berkas `.html` tanpa folder, atau null bila tautannya bukan berkas
     * halaman relatif (mis. `mailto:`, `tel:`, `#anchor`, alamat luar).
     */
    private function pathSlug(string $href): ?string
    {
        $path = (string) preg_replace('/[?#].*$/', '', str_replace('\\', '/', trim($href)));

        if ($path === '' || str_contains($path, ':')) {
            return null;
        }

        $path = (string) preg_replace('#^(\./|\.\./)+#', '', $path);
        $path = (string) preg_replace('#^pages/#i', '', $path);

        if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'html') {
            return null;
        }

        $slug = strtolower((string) pathinfo($path, PATHINFO_FILENAME));

        return $slug !== '' ? $slug : null;
    }

    /**
     * Daftar <ul> navigasi di <header> yang isinya hanya tautan halaman
     * diganti wp_nav_menu, supaya menu WordPress sungguhan yang tampil.
     * Daftar yang memuat tautan kontak (WhatsApp/telepon/email) dibiarkan:
     * tombol kontak itu bagian desain, bukan navigasi halaman.
     */
    private function convertHeaderNavToMenu(string $html): string
    {
        return (string) preg_replace_callback('/<header\b[^>]*>.*?<\/header>/is', function (array $header): string {
            return (string) preg_replace_callback('/<ul\b([^>]*)>(.*?)<\/ul>/is', function (array $list): string {
                $inner = $list[2];

                if (!str_contains($inner, 'siteflow_page_url(')
                    || preg_match('/wa\.me|tel:|mailto:/i', $inner)) {
                    return $list[0];
                }

                return '<?php siteflow_nav_menu(' . var_export($this->classAttribute($list[1]), true) . '); ?>';
            }, $header[0], 1);
        }, $html);
    }

    private function classAttribute(string $attributes): string
    {
        if (preg_match('/class\s*=\s*(["\'])(.*?)\1/i', $attributes, $match)) {
            $class = trim($match[2]);

            if ($class !== '') {
                return $class;
            }
        }

        return 'links';
    }

    private function bodyInner(string $html): string
    {
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $match)) {
            return $match[1];
        }

        return $html;
    }

    private function stripKnownMenuScript(string $html): string
    {
        return (string) preg_replace_callback('/<script\b[^>]*>(.*?)<\/script\s*>/is', function (array $match): string {
            $script = strtolower(str_replace('"', "'", preg_replace('/\s+/', '', trim($match[1]))));
            $menuToggle = strtolower("consttoggle=document.querySelector('.menu-toggle');constmenu=document.querySelector('.navlinks');toggle.addEventListener('click',()=>{constopen=menu.classList.toggle('open');toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'tutupmenu':'bukamenu')});menu.addEventListener('click',e=>{if(e.target.closest('a')){menu.classList.remove('open');toggle.setAttribute('aria-expanded','false')}});");

            if ($script !== $menuToggle) {
                throw new RuntimeException('Skrip kandidat tidak didukung; tema hasil build tidak boleh memuat JavaScript.');
            }

            return '';
        }, $html);
    }

    private function hasStaticMobileMenu(string $html): bool
    {
        return preg_match('/class\s*=\s*(["\'])[^"\']*\bmenu-toggle\b[^"\']*\1/i', $html) === 1
            && preg_match('/class\s*=\s*(["\'])[^"\']*\bnavlinks\b[^"\']*\1/i', $html) === 1;
    }

    /**
     * Membuang chrome global halaman isi: <header> pertama dan <footer>
     * terakhir. Tema sudah punya keduanya dari index.html.
     */
    private function stripChrome(string $html): string
    {
        $html = (string) preg_replace('/<header\b[^>]*>.*?<\/header>/is', '', $html, 1);

        return (string) preg_replace('/<footer\b[^>]*>.*?<\/footer>\s*$/is', '', $html, 1);
    }

    /**
     * page.php sudah membungkus isi dengan <main class="siteflow-page">.
     * Pembungkus <main> dari kandidat dibuang; kalau membawa kelas atau id,
     * tag-nya diganti <div> supaya kelas CSS tetap menempel tanpa <main> ganda.
     */
    private function unwrapMainWrapper(string $html): string
    {
        return (string) preg_replace_callback('/<main\b([^>]*)>(.*?)<\/main>/is', function (array $match): string {
            $attributes = trim($match[1]);

            return $attributes === ''
                ? $match[2]
                : '<div' . $match[1] . '>' . $match[2] . '</div>';
        }, $html, 1);
    }

    private function pageTitle(string $html, string $fallback): string
    {
        if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $match)) {
            $title = trim((string) preg_replace('/\s+/', ' ', strip_tags($match[1])));

            if ($title !== '') {
                return $title;
            }
        }

        return $fallback;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function extractStyles(string $html): array
    {
        $blocks = [];

        $clean = (string) preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function (array $match) use (&$blocks): string {
            $css = trim($match[1]);

            if ($css !== '') {
                $blocks[] = $css;
            }

            return '';
        }, $html);

        return [$clean, $blocks];
    }

    private function unwrapLinks(string $html, string $marker): string
    {
        $pattern = '/<a\b[^>]*(?<![-\w:])href\s*=\s*(["\'])' . preg_quote($marker, '/') . '\1[^>]*>(.*?)<\/a>/is';

        return (string) preg_replace_callback($pattern, fn (array $matches): string => $matches[2], $html);
    }

    private function replaceExactHref(string $html, string $marker, string $url): string
    {
        $pattern = '/(?<![-\w:])href\s*=\s*(["\'])' . preg_quote($marker, '/') . '\1/i';
        $replacement = 'href=$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $url) . '$1';

        return (string) preg_replace($pattern, $replacement, $html);
    }

    /**
     * Slug 'layanan' adalah awalan slug 'layanan-detail'; penanda dianggap
     * tersisa hanya kalau tidak disambung huruf, angka, garis bawah, strip,
     * atau ekstensi berkas.
     */
    private function hasMarker(string $html, string $marker): bool
    {
        return preg_match('/' . preg_quote($marker, '/') . '(?![a-zA-Z0-9_.-])/i', $html) === 1;
    }

    private function whatsappDigits(string $value): string
    {
        $digits = (string) preg_replace('/[^0-9]/', '', $value);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    /**
     * Build ulang memakai folder yang sama; sisa gambar dan isi halaman dari
     * percobaan sebelumnya dibuang agar tidak ikut terkemas.
     */
    private function clearGeneratedAssets(string $themeDir): void
    {
        foreach (['images', 'content'] as $dir) {
            $path = $themeDir . '/' . $dir;

            if (!is_dir($path)) {
                continue;
            }

            foreach (glob($path . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function copyCandidateAssets(string $candidateDir, string $themeDir, string $imagesDir): void
    {
        $previewPath = $candidateDir . '/preview.png';
        if (is_file($previewPath)) {
            copy($previewPath, $themeDir . '/screenshot.png');
        } elseif (is_file($themeDir . '/screenshot.png')) {
            unlink($themeDir . '/screenshot.png');
        }

        $files = glob($candidateDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            $basename = basename($file);
            if ($basename === 'preview.png') {
                continue;
            }
            copy($file, $imagesDir . '/' . $basename);
        }
    }

    /**
     * @param array<string, string> $imageFiles
     * @param list<string> $buttonClasses
     * @param list<string> $imageClasses
     * @param list<string> $headingClasses
     */
    private function writeStyleCss(
        string $frontCss,
        string $contentCss,
        string $themeDir,
        Project $project,
        array $imageFiles,
        array $buttonClasses = [],
        array $imageClasses = [],
        array $headingClasses = []
    ): void {
        $frontCss = $this->substituteImageTokens($frontCss, $imageFiles);
        $contentCss = $this->substituteImageTokens($contentCss, $imageFiles);

        if (str_contains($frontCss . $contentCss, '__EXITO_')) {
            throw new RuntimeException('Stylesheet masih memuat penanda __EXITO_ yang tidak dikenal.');
        }

        // Beranda dan bilah atas/bawah memakai lingkup halaman; halaman isi
        // memakai lingkup pembungkusnya sendiri supaya tidak saling timpa.
        // Halaman isi hanya ditulis lewat jembatan: salinan berlingkup ganda
        // itu sudah menang di halaman depan maupun kanvas, jadi menulis versi
        // satu lingkup lagi hanya menggandakan berat stylesheet.
        $bridge = trim(
            $this->cssBridge->resetRules($buttonClasses)
            . "\n"
            . $this->cssBridge->build($frontCss, $buttonClasses, $imageClasses, $headingClasses)
            . "\n"
            . $this->cssBridge->build($contentCss, $buttonClasses, $imageClasses, $headingClasses)
        );

        // Kit warna Elementor memberi warna pada semua judul dan blok teks
        // halaman (bobot dua kelas). Teks yang di markah tidak punya warna
        // sendiri — hanya mewarisi dari pita gelap — jadi putihnya tertutup
        // kit. Warisan dipulihkan dengan aturan berbobot tiga kelas, ditulis
        // sebelum jembatan: salinan jembatan berbobot empat kelas sehingga
        // warna yang memang ditulis di markah tetap menang.
        $inherit = $this->cssBridge->colourInheritanceRules($frontCss . "\n" . $contentCss, $buttonClasses);

        $themeName = str_replace(['*/', "\n", "\r"], '', $project->name);
        $clientName = str_replace(['*/', "\n", "\r"], '', (string) ($project->client_name ?: $project->name));
        $textDomain = $this->textDomain($project);

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

        $css = trim($frontCss);

        file_put_contents(
            $themeDir . '/style.css',
            $header . "\n" . $css
            . ($inherit !== '' ? "\n\n" . $inherit : '')
            . ($bridge !== '' ? "\n\n" . $bridge : '')
            . "\n\n" . $this->pageScopeBaselineCss()
            . "\n\n" . $this->photoBaselineCss($frontCss . "\n" . $contentCss)
            . "\n"
        );
    }

    /**
     * Kontainer akar hasil terjemahan ikut lebar konten kit Elementor (760px),
     * bukan lebar layar seperti di markah aslinya. Kolom isi yang menghitung
     * lebarnya dari induk (100% dikurangi tepi) jadi menyusut sampai teksnya
     * saling menimpa. Pembungkus halaman dikembalikan ke alur blok penuh.
     */
    private function pageScopeBaselineCss(): string
    {
        $lingkup = [];

        foreach (['.siteflow-page.siteflow-page', '.elementor-edit-area.elementor-edit-area'] as $scope) {
            $lingkup[] = $scope . ' .siteflow-page-scope';
        }

        return implode(',', $lingkup)
            . '{width:100%;max-width:none;min-width:0;display:block;align-items:stretch;padding:0}';
    }

    /**
     * @param list<string> $cssBlocks
     */
    private function joinCssBlocks(array $cssBlocks): string
    {
        $unique = [];

        foreach ($cssBlocks as $block) {
            $block = trim($block);

            if ($block !== '') {
                $unique[md5($block)] = $block;
            }
        }

        return implode("\n", array_values($unique));
    }

    /**
     * CSS halaman isi diberi awalan pembungkus halamannya. Aturan akar dan
     * at-rule global dari halaman isi dibuang: palet dan reset sudah dibawa
     * beranda, dan menyalinnya lagi akan bocor ke seluruh situs. Halaman yang
     * CSS-nya identik berbagi satu aturan dengan daftar pemilih, bukan satu
     * salinan per halaman.
     *
     * @param array<string, array{title: string, html: string, css: list<string>}> $contentPages
     * @param array<string, string> $pageMarkup markah akhir tiap halaman isi, setelah chrome dibuang
     */
    private function scopeContentCss(array $contentPages, array $pageMarkup): string
    {
        $grup = [];

        foreach ($contentPages as $slug => $page) {
            $blocks = $this->joinCssBlocks($page['css']);

            if ($blocks === '') {
                continue;
            }

            $kunci = md5($blocks);
            $grup[$kunci]['blocks'] = $blocks;
            $grup[$kunci]['kelas'][] = '.' . $this->pageScopeClass($slug);
            $grup[$kunci]['dipakai'] = array_merge(
                $grup[$kunci]['dipakai'] ?? [],
                $this->classesInMarkup($pageMarkup[$slug] ?? '')
            );
        }

        $parts = [];

        foreach ($grup as $grupCss) {
            $hidup = $this->cssBridge->pruneUnusedClasses($grupCss['blocks'], array_unique($grupCss['dipakai']));

            if (trim($hidup) === '') {
                continue;
            }

            $scoped = $this->cssBridge->scopeCss($hidup, implode(', ', $grupCss['kelas']));

            foreach ([$scoped['at'], $scoped['scoped']] as $part) {
                if ($part !== '') {
                    $parts[md5($part)] = $part;
                }
            }
        }

        return implode("\n", array_values($parts));
    }

    /**
     * Nama kelas yang benar-benar dipakai sebuah markah halaman.
     *
     * @return list<string>
     */
    private function classesInMarkup(string $markup): array
    {
        $kelas = [];

        foreach (preg_match_all('/\bclass\s*=\s*["\']([^"\']*)["\']/i', $markup, $cocok) ? $cocok[1] : [] as $nilai) {
            foreach (preg_split('/\s+/', trim($nilai)) as $satu) {
                if ($satu !== '' && preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $satu) === 1) {
                    $kelas[$satu] = true;
                }
            }
        }

        return array_keys($kelas);
    }

    /**
     * @param array<string, string> $imageFiles
     */
    private function substituteImageTokens(string $css, array $imageFiles): string
    {
        return (string) preg_replace_callback('/__EXITO_IMAGE:([a-zA-Z0-9_.-]+)/', function (array $match) use ($imageFiles): string {
            return 'images/' . $this->resolveImageFile($match[1], $imageFiles);
        }, $css);
    }

    /**
     * Halaman isi dibungkus kelas lingkup sendiri. CSS halaman itu lalu
     * diberi awalan kelas ini, sehingga aturan `.hero` di halaman Tentang
     * tidak lagi menimpa `.hero` beranda.
     */
    private function wrapPageScope(string $slug, string $markup): string
    {
        return '<div class="siteflow-page-scope siteflow-page-' . $this->pageScopeSlug($slug) . '">'
            . trim($markup) . '</div>';
    }

    private function pageScopeClass(string $slug): string
    {
        return 'siteflow-page-' . $this->pageScopeSlug($slug);
    }

    private function pageScopeSlug(string $slug): string
    {
        $safe = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)), '-');

        return $safe !== '' ? $safe : 'halaman';
    }

    /**
     * Memberi bingkai foto satu kelas peran dan satu kelas slot pada kontainer
     * terdekat. Foto tanpa kontainer yang layak (mis. langsung di <section>)
     * dibungkus <figure> supaya rasio dan keterangannya tetap terkendali.
     */
    private function tagPhotoContainers(string $html): string
    {
        if (!str_contains($html, '[[siteflow:gambar:')) {
            return $html;
        }

        $tagPattern = '/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/s';

        if (preg_match_all($tagPattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return $html;
        }

        $stack = [];
        $inserts = [];
        $wraps = [];

        foreach ($matches as $match) {
            $tag = $match[0][0];
            $offset = $match[0][1];
            $closing = $match[1][0] === '/';
            $name = strtolower($match[2][0]);

            if ($closing) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['name'] === $name) {
                        $stack = array_slice($stack, 0, $i);
                        break;
                    }
                }

                continue;
            }

            if ($name === 'img') {
                $slot = $this->photoSlotFromTag($match[3][0]);

                if ($slot === null) {
                    continue;
                }

                $classes = 'siteflow-photo siteflow-photo-' . $slot;
                $container = null;

                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if (in_array($stack[$i]['name'], ['section', 'article', 'main', 'header', 'footer'], true)) {
                        break;
                    }

                    if (in_array($stack[$i]['name'], self::PHOTO_CONTAINERS, true)) {
                        $container = $stack[$i];
                        break;
                    }
                }

                if ($container !== null) {
                    $inserts[$container['offset']] = $this->mergeClasses($inserts[$container['offset']] ?? '', $classes);
                } else {
                    $wraps[$offset] = $classes;
                }

                continue;
            }

            if (in_array($name, self::VOID_TAGS, true) || str_ends_with(rtrim($tag), '/>')) {
                continue;
            }

            $stack[] = ['name' => $name, 'offset' => $offset];
        }

        if ($inserts === [] && $wraps === []) {
            return $html;
        }

        $result = '';
        $cursor = 0;

        foreach ($matches as $match) {
            $tag = $match[0][0];
            $offset = $match[0][1];

            if (isset($wraps[$offset])) {
                $tag = '<figure class="' . $wraps[$offset] . '">' . $tag . '</figure>';
            } elseif (isset($inserts[$offset])) {
                $tag = $this->addClassesToTag($tag, $inserts[$offset]);
            }

            $result .= substr($html, $cursor, $offset - $cursor) . $tag;
            $cursor = $offset + strlen($match[0][0]);
        }

        return $result . substr($html, $cursor);
    }

    private function photoSlotFromTag(string $attributes): ?string
    {
        if (preg_match('/src\s*=\s*(["\'])\[\[siteflow:gambar:([A-Za-z0-9_.-]+)\]\]\1/i', $attributes, $match) !== 1) {
            return null;
        }

        $slot = (string) preg_replace('/\.(jpe?g|png|webp|gif)$/i', '', $match[2]);
        $slot = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($slot)), '-');

        return $slot !== '' ? $slot : 'foto';
    }

    private function addClassesToTag(string $tag, string $classes): string
    {
        $classes = trim($classes);

        if ($classes === '') {
            return $tag;
        }

        if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/is', $tag, $match, PREG_OFFSET_CAPTURE) === 1) {
            $merged = $this->mergeClasses($match[2][0], $classes);
            $start = $match[0][1];

            return substr($tag, 0, $start) . 'class="' . $merged . '"' . substr($tag, $start + strlen($match[0][0]));
        }

        return (string) preg_replace('/^<([a-zA-Z][a-zA-Z0-9-]*)/', '<$1 class="' . $classes . '"', $tag, 1);
    }

    private function mergeClasses(string $existing, string $added): string
    {
        $classes = array_merge(
            preg_split('/\s+/', trim($existing)) ?: [],
            preg_split('/\s+/', trim($added)) ?: []
        );

        return implode(' ', array_values(array_unique(array_filter($classes, static fn (string $class): bool => $class !== ''))));
    }

    /**
     * Foto klien: satu peran, satu rasio, satu titik fokus. Ditaruh sesudah
     * jembatan Elementor supaya aturan tampilan tiap halaman tidak lagi
     * menentukan tinggi piksel atau memotong foto dengan bingkai dekoratif.
     */
    private function photoBaselineCss(string $sourceCss = ''): string
    {
        $lines = [
            '/* Foto klien: satu peran, satu rasio, satu titik fokus (rancangan 2026-11-02). */',
        ];

        $cover = $this->coverPhotoSlots($sourceCss);

        foreach (self::PHOTO_RATIOS as $slot => $ratio) {
            $isCover = in_array($slot, $cover, true);

            $lines[] = $this->photoSelectors($slot)
                . ($isCover
                    ? '{position:absolute;inset:0;width:100%;height:100%;min-height:0;max-height:none;overflow:hidden;border-radius:0}'
                    : '{height:auto;min-height:0;max-height:none;overflow:visible;border-radius:0}');
            $lines[] = $this->photoSelectors($slot, '::before') . ',' . $this->photoSelectors($slot, '::after') . '{content:none;display:none;border:0;border-radius:0;background:none;box-shadow:none}';
            $lines[] = $this->photoSelectors($slot, '>.elementor-widget') . ',' . $this->photoSelectors($slot, '>.elementor-widget>.elementor-widget-container')
                . ($isCover ? '{width:100%;height:100%;min-height:0;max-height:none}' : '{height:auto;min-height:0;max-height:none}');
            $lines[] = $this->photoSelectors($slot, ' img') . '{display:block;width:100%;'
                . ($isCover
                    ? 'height:100%;min-height:0;max-height:none;max-width:100%;object-fit:cover;'
                    : 'height:auto;min-height:0;max-height:none;max-width:100%;aspect-ratio:' . $ratio . ';object-fit:cover;')
                . 'object-position:var(--siteflow-foto-fokus,50% 40%);border-radius:' . ($isCover ? '0' : '4px')
                . ';filter:saturate(.94) contrast(1.04)}';
            $lines[] = $this->photoSelectors($slot, ' .photo-caption') . ',' . $this->photoSelectors($slot, ' figcaption') . '{position:static;inset:auto;display:block;width:auto;max-width:none;margin:10px 0 0;padding:10px 0 0;background:none;border:0;border-top:1px solid rgba(0,0,0,.16);border-top-color:color-mix(in srgb,currentColor 22%,transparent);border-radius:0;box-shadow:none;font-size:13px;line-height:1.5;letter-spacing:0;text-align:left;color:var(--siteflow-meta,var(--tinta,var(--ink,currentColor)))}';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Slot foto yang di markah aslinya menutupi seluruh blok (position:absolute
     * pada <img>): foto itu memang latar belakang, bukan gambar berbingkai.
     * Aturan dasar foto tidak boleh memulangkannya ke alur biasa, kalau tidak
     * bloknya kehilangan gambarnya dan lapisan di atasnya jadi percuma.
     *
     * @return list<string>
     */
    private function coverPhotoSlots(string $css): array
    {
        if (trim($css) === '') {
            return [];
        }

        $slots = [];

        foreach (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $cocok, PREG_SET_ORDER) ? $cocok : [] as $rule) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));

            if (preg_match('/position\s*:\s*absolute/i', $rule[2]) !== 1) {
                continue;
            }

            if (preg_match('/(?:^|[\s>+~])img$/i', $selector) !== 1) {
                continue;
            }

            $induk = (string) preg_replace('/(?:\s+|>)\s*img$/i', '', $selector);

            if (preg_match_all('/\.([a-zA-Z_][a-zA-Z0-9_-]*)/', $induk, $kelas) < 1) {
                continue;
            }

            $pemilik = (string) end($kelas[1]);

            if (isset(self::PHOTO_RATIOS[$pemilik])) {
                $slots[$pemilik] = true;
            }
        }

        return array_keys($slots);
    }

    /**
     * Satu daftar pemilih berisi kedua lingkup, bukan dua aturan kembar:
     * deklarasinya sama, jadi cukup ditulis sekali.
     */
    private function photoSelectors(string $slot, string $suffix = ''): string
    {
        $selectors = [];

        foreach (self::PHOTO_SCOPES as $scope) {
            $selectors[] = $scope . ' .siteflow-photo.siteflow-photo-' . $slot . $suffix;
        }

        return implode(',', $selectors);
    }

    private function writeFunctionsPhp(string $themeDir, Project $project): void
    {
        $safeName = str_replace(['*/', "\r", "\n"], '', $project->name);

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

        file_put_contents($themeDir . '/functions.php', $content . $this->importerCode());
    }

    /**
     * Kode importer ditulis tangan, deterministik, tanpa jaringan. Berjalan
     * saat admin pertama membuka wp-admin: isi halaman dibaca dari
     * content/<slug>.json (elemen Elementor) dengan cadangan content/<slug>.html,
     * foto diunggah ke pustaka media, halaman dibuat, menu dipasang, dan
     * Beranda dipasang sebagai halaman depan statis.
     */
    private function importerCode(): string
    {
        return <<<'PHP'
// ---------------------------------------------------------------------
// Importer halaman, pustaka media, dan menu - ditulis saat build.
// Berjalan saat admin pertama membuka wp-admin. Satu tema, nol plugin.
// ---------------------------------------------------------------------

function siteflow_theme_meta(): array
{
    static $meta = null;

    if (is_array($meta)) {
        return $meta;
    }

    $path = get_stylesheet_directory() . '/content/halaman.json';
    $raw = is_readable($path) ? (string) file_get_contents($path) : '';
    $decoded = json_decode($raw, true);

    $meta = is_array($decoded) ? $decoded : [];

    return $meta;
}

function siteflow_page_key(string $slug): string
{
    $meta = siteflow_theme_meta();
    $owner = isset($meta['kunci']) && is_string($meta['kunci']) && $meta['kunci'] !== '' ? $meta['kunci'] : 'siteflow';

    return $owner . ':' . $slug;
}

function siteflow_page_id(string $slug): int
{
    $stored = get_option('siteflow_page_ids', []);

    if (is_array($stored) && !empty($stored[$slug])) {
        $id = (int) $stored[$slug];

        if ($id > 0 && get_post_status($id) !== false) {
            return $id;
        }
    }

    $found = get_posts([
        'post_type' => 'page',
        'post_status' => 'any',
        'numberposts' => 1,
        'fields' => 'ids',
        'meta_key' => '_siteflow_page_key',
        'meta_value' => siteflow_page_key($slug),
    ]);

    return $found ? (int) $found[0] : 0;
}

function siteflow_page_url(string $slug): string
{
    if ($slug === '' || $slug === 'home' || $slug === 'beranda') {
        return home_url('/');
    }

    $id = siteflow_page_id($slug);

    return $id > 0 ? (string) get_permalink($id) : home_url('/');
}

// Token di data Elementor bisa berdiri sendiri (image.url) atau terselip di
// markah teks (src="...", href="..."). Keduanya diganti di nilai string.
function siteflow_replace_tokens($value, array $media_urls, array $page_ids)
{
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = siteflow_replace_tokens($item, $media_urls, $page_ids);
        }

        return $value;
    }

    if (!is_string($value) || strpos($value, '[[siteflow:') === false) {
        return $value;
    }

    $value = (string) preg_replace_callback('/\[\[siteflow:gambar:([A-Za-z0-9_.-]+)\]\]/', function (array $match) use ($media_urls): string {
        $filename = $match[1];

        if (isset($media_urls[$filename]) && $media_urls[$filename] !== '') {
            return (string) $media_urls[$filename];
        }

        return (string) get_stylesheet_directory_uri() . '/images/' . $filename;
    }, $value);

    return (string) preg_replace_callback('/\[\[siteflow:halaman:([a-z0-9-]+)\]\]/', function (array $match) use ($page_ids): string {
        $id = isset($page_ids[$match[1]]) ? (int) $page_ids[$match[1]] : 0;

        return (string) ($id > 0 ? get_permalink($id) : home_url('/'));
    }, $value);
}

function siteflow_elementor_payload(string $slug, array $media_urls, array $page_ids): array
{
    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
        return [];
    }

    $path = get_stylesheet_directory() . '/content/' . $slug . '.json';

    if (!is_readable($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (!is_array($decoded) || !isset($decoded['elementor']) || !is_array($decoded['elementor']) || $decoded['elementor'] === []) {
        return [];
    }

    return siteflow_replace_tokens($decoded['elementor'], $media_urls, $page_ids);
}

function siteflow_nav_items_html(): string
{
    $meta = siteflow_theme_meta();
    $items = isset($meta['nav']) && is_array($meta['nav']) ? $meta['nav'] : [];
    $html = '';

    foreach ($items as $item) {
        if (!is_array($item) || empty($item['slug'])) {
            continue;
        }

        $label = isset($item['label']) && is_string($item['label']) && $item['label'] !== ''
            ? $item['label']
            : (string) $item['slug'];

        $html .= '<li class="menu-item"><a href="' . esc_url(siteflow_page_url((string) $item['slug'])) . '">'
            . esc_html($label) . '</a></li>';
    }

    return $html;
}

// Selama menu belum terpasang (importer belum jalan), daftar halaman dari
// content/halaman.json tetap tampil - bilah navigasi tidak pernah kosong.
function siteflow_nav_menu(string $class = 'links'): void
{
    if (has_nav_menu('primary')) {
        wp_nav_menu([
            'theme_location' => 'primary',
            'container' => false,
            'menu_class' => $class,
            'items_wrap' => '<ul class="' . esc_attr($class) . '">%3$s</ul>',
            'depth' => 1,
            'fallback_cb' => false,
        ]);

        return;
    }

    echo '<ul class="' . esc_attr($class) . '">' . siteflow_nav_items_html() . '</ul>';
}

// Unggah semua foto tema ke pustaka media sekali; impor ulang memakai
// lampiran yang sama, jadi tidak ada foto dobel.
function siteflow_import_media(): array
{
    $meta = siteflow_theme_meta();
    $files = isset($meta['gambar']) && is_array($meta['gambar']) ? $meta['gambar'] : [];
    $alts = isset($meta['gambar_alt']) && is_array($meta['gambar_alt']) ? $meta['gambar_alt'] : [];

    if (!$files || !function_exists('wp_upload_bits')) {
        return [];
    }

    $imageFunctions = ABSPATH . 'wp-admin/includes/image.php';
    $fileFunctions = ABSPATH . 'wp-admin/includes/file.php';

    if (is_readable($imageFunctions)) {
        require_once $imageFunctions;
    }

    if (is_readable($fileFunctions)) {
        require_once $fileFunctions;
    }

    if (!function_exists('wp_generate_attachment_metadata')) {
        return [];
    }

    // Simpan lampiran per tema, bukan per situs. Nama berkas dua klien bisa
    // sama (hero.png), jadi peta situs bersama akan membuat tema baru memakai
    // ulang foto klien lama.
    $opsi_lampiran = 'siteflow_media_ids_' . get_stylesheet();
    $stored = get_option($opsi_lampiran, []);
    $stored = is_array($stored) ? $stored : [];
    $urls = [];

    foreach ($files as $filename) {
        if (!is_string($filename) || $filename === '' || str_contains($filename, '..')) {
            continue;
        }

        $existing = isset($stored[$filename]) ? (int) $stored[$filename] : 0;

        if ($existing > 0 && get_post_status($existing) !== false) {
            $urls[$filename] = (string) wp_get_attachment_url($existing);

            if (!empty($alts[$filename]) && is_string($alts[$filename])) {
                update_post_meta($existing, '_wp_attachment_image_alt', sanitize_text_field($alts[$filename]));
            }

            continue;
        }

        $path = get_stylesheet_directory() . '/images/' . $filename;

        if (!is_file($path)) {
            continue;
        }

        $bytes = file_get_contents($path);

        if ($bytes === false) {
            continue;
        }

        $upload = wp_upload_bits($filename, null, $bytes);

        if (!empty($upload['error']) || empty($upload['file'])) {
            continue;
        }

        $filetype = wp_check_filetype($upload['file']);
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_status' => 'inherit',
        ], $upload['file']);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            continue;
        }

        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));

        if (!empty($alts[$filename]) && is_string($alts[$filename])) {
            update_post_meta((int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alts[$filename]));
        }

        $stored[$filename] = (int) $attachment_id;
        $urls[$filename] = (string) wp_get_attachment_url($attachment_id);
    }

    update_option($opsi_lampiran, $stored);

    return $urls;
}

function siteflow_page_markup(string $slug, array $media_urls, array $page_ids): string
{
    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
        return '';
    }

    $path = get_stylesheet_directory() . '/content/' . $slug . '.html';

    if (!is_readable($path)) {
        return '';
    }

    $html = (string) file_get_contents($path);

    // Foto yang gagal masuk pustaka media: <img>-nya dibuang utuh (rancangan 5).
    $html = (string) preg_replace_callback('/<img\b[^>]*src\s*=\s*(["\'])\[\[siteflow:gambar:([A-Za-z0-9_.-]+)\]\]\1[^>]*>/i', function (array $match) use ($media_urls): string {
        $url = isset($media_urls[$match[2]]) ? (string) $media_urls[$match[2]] : '';

        if ($url === '') {
            return '';
        }

        return str_replace('[[siteflow:gambar:' . $match[2] . ']]', esc_url($url), $match[0]);
    }, $html);

    // Sisa token gambar di luar <img> (mis. latar CSS) memakai berkas tema.
    $html = (string) preg_replace_callback('/\[\[siteflow:gambar:([A-Za-z0-9_.-]+)\]\]/', function (array $match) use ($media_urls): string {
        $filename = $match[1];

        if (isset($media_urls[$filename]) && $media_urls[$filename] !== '') {
            return esc_url((string) $media_urls[$filename]);
        }

        return esc_url(get_stylesheet_directory_uri() . '/images/' . $filename);
    }, $html);

    $html = (string) preg_replace_callback('/\[\[siteflow:halaman:([a-z0-9-]+)\]\]/', function (array $match) use ($page_ids): string {
        $id = isset($page_ids[$match[1]]) ? (int) $page_ids[$match[1]] : 0;

        return esc_url($id > 0 ? (string) get_permalink($id) : home_url('/'));
    }, $html);

    return $html;
}

// Menu adalah menu WordPress biasa: klien menambah halaman sekali di
// Appearance > Menus, semua halaman ikut. Impor ulang hanya mengganti item
// buatan tema ini (dilacak lewat option), jadi item buatan klien tidak
// pernah terhapus.
function siteflow_import_menu(array $page_ids): void
{
    if (!function_exists('wp_create_nav_menu')) {
        $navMenuFile = ABSPATH . 'wp-admin/includes/nav-menu.php';

        if (is_readable($navMenuFile)) {
            require_once $navMenuFile;
        }
    }

    if (!function_exists('wp_create_nav_menu') || !$page_ids) {
        return;
    }

    $meta = siteflow_theme_meta();
    $nav = isset($meta['nav']) && is_array($meta['nav']) ? $meta['nav'] : [];
    $name = 'Menu Utama';
    $menu_id = (int) get_option('siteflow_nav_menu_id');

    if (!$menu_id || !wp_get_nav_menu_object($menu_id)) {
        $existing = wp_get_nav_menu_object($name);

        if ($existing) {
            $menu_id = (int) $existing->term_id;
        } else {
            $created = wp_create_nav_menu($name);

            if (is_wp_error($created) || !$created) {
                return;
            }

            $menu_id = (int) $created;
        }
    }

    if ($menu_id < 1) {
        return;
    }

    update_option('siteflow_nav_menu_id', $menu_id);

    $owned = get_option('siteflow_nav_item_ids', []);
    $owned = is_array($owned) ? $owned : [];

    foreach ($owned as $item_id) {
        wp_delete_post((int) $item_id, true);
    }

    $item_ids = [];
    $home_id = wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' => 'Beranda',
        'menu-item-type' => 'custom',
        'menu-item-url' => home_url('/'),
        'menu-item-status' => 'publish',
    ]);

    if ($home_id && !is_wp_error($home_id)) {
        $item_ids[] = (int) $home_id;
    }

    foreach ($nav as $item) {
        if (!is_array($item) || empty($item['slug'])) {
            continue;
        }

        $page_id = isset($page_ids[(string) $item['slug']]) ? (int) $page_ids[(string) $item['slug']] : 0;

        if ($page_id < 1) {
            continue;
        }

        $item_id = wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => (string) ($item['label'] ?? $item['slug']),
            'menu-item-type' => 'post_type',
            'menu-item-object' => 'page',
            'menu-item-object-id' => $page_id,
            'menu-item-status' => 'publish',
        ]);

        if ($item_id && !is_wp_error($item_id)) {
            $item_ids[] = (int) $item_id;
        }
    }

    update_option('siteflow_nav_item_ids', $item_ids);

    $locations = get_theme_mod('nav_menu_locations', []);
    $locations = is_array($locations) ? $locations : [];
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}

// Palet dibaca dari stylesheet tema supaya warna kit Elementor mengikuti
// warna yang benar-benar dipakai halaman, bukan warna bawaan Elementor.
function siteflow_theme_palette(): array
{
    $path = get_stylesheet_directory() . '/style.css';
    $css = is_readable($path) ? (string) file_get_contents($path) : '';

    if (!preg_match_all('/:root\s*\{([^}]*)\}/', $css, $blocks)) {
        return [];
    }

    $palette = [];

    foreach ($blocks[1] as $block) {
        preg_match_all('/--([\w-]+)\s*:\s*([^;}]+)/', $block, $vars, PREG_SET_ORDER);

        foreach ($vars as $var) {
            $name = strtolower($var[1]);

            if ($var[2] !== '' && !isset($palette[$name])) {
                $palette[$name] = trim($var[2]);
            }
        }
    }

    return $palette;
}

// Kanvas editor Elementor mewarnai judul dan tombol dari warna global kit,
// bukan dari CSS tema. Selama kit masih memakai warna bawaan Elementor,
// samakan dengan palet tema; warna yang sudah diubah admin tidak disentuh.
function siteflow_sync_elementor_kit(): void
{
    if (!class_exists('\Elementor\Plugin')) {
        return;
    }

    $kit_id = (int) get_option('elementor_active_kit');

    if ($kit_id < 1) {
        return;
    }

    $palette = siteflow_theme_palette();
    $ink = isset($palette['tinta']) ? $palette['tinta'] : '';
    $forest = isset($palette['hutan']) ? $palette['hutan'] : '';

    if (preg_match('/^#[0-9a-f]{6}$/i', $ink) !== 1 || preg_match('/^#[0-9a-f]{6}$/i', $forest) !== 1) {
        return;
    }

    $wanted = ['primary' => $ink, 'secondary' => $forest, 'text' => $ink, 'accent' => $forest];
    $factory = ['primary' => '#6EC1E4', 'secondary' => '#54595F', 'text' => '#7A7A7A', 'accent' => '#61CE70'];

    $settings = get_post_meta($kit_id, '_elementor_page_settings', true);
    $settings = is_array($settings) ? $settings : [];
    $colors = isset($settings['system_colors']) && is_array($settings['system_colors']) ? $settings['system_colors'] : [];
    $by_id = [];

    foreach ($colors as $color) {
        if (is_array($color) && isset($color['_id'])) {
            $by_id[(string) $color['_id']] = $color;
        }
    }

    $changed = false;

    foreach ($factory as $id => $default) {
        $current = isset($by_id[$id]['color']) ? (string) $by_id[$id]['color'] : '';

        // Warna selain bawaan Elementor berarti sudah dipilih admin.
        if ($current !== '' && strcasecmp($current, $default) !== 0) {
            continue;
        }

        if (strcasecmp($current, $wanted[$id]) === 0) {
            continue;
        }

        $by_id[$id] = [
            '_id' => $id,
            'title' => isset($by_id[$id]['title']) ? $by_id[$id]['title'] : ucfirst($id),
            'color' => $wanted[$id],
        ];
        $changed = true;
    }

    if (!$changed) {
        return;
    }

    $ordered = [];

    foreach (array_keys($factory) as $id) {
        if (isset($by_id[$id])) {
            $ordered[] = $by_id[$id];
        }
    }

    $settings['system_colors'] = $ordered;

    update_post_meta($kit_id, '_elementor_page_settings', wp_slash($settings));
}

// Dua lintasan: lintasan pertama membuat semua halaman, lintasan kedua
// mengisi tautan antar-halaman yang baru punya alamat setelah semua ada.
function siteflow_import_pages(): int
{
    $meta = siteflow_theme_meta();
    $pages = isset($meta['halaman']) && is_array($meta['halaman']) ? $meta['halaman'] : [];

    if (!$pages || !function_exists('wp_insert_post')) {
        return 0;
    }

    $media_urls = siteflow_import_media();
    $page_ids = [];
    $preserved = [];
    $written = 0;

    foreach ($pages as $page) {
        if (!is_array($page) || empty($page['slug'])) {
            continue;
        }

        $slug = (string) $page['slug'];

        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            continue;
        }

        $existing = siteflow_page_id($slug);
        $elementor = siteflow_elementor_payload($slug, $media_urls, $page_ids);

        // Halaman yang sudah disunting di Elementor sesudah impor terakhir
        // tidak ditimpa. Tema baru hanya memperbarui halaman yang belum disentuh.
        if ($existing > 0 && $elementor !== []) {
            $stored_hash = (string) get_post_meta($existing, '_siteflow_elementor_hash', true);
            $current_data = (string) get_post_meta($existing, '_elementor_data', true);

            if ($stored_hash !== '' && md5($current_data) !== $stored_hash) {
                $preserved[$slug] = true;
                $page_ids[$slug] = $existing;
                continue;
            }
        }

        $data = [
            'post_title' => (string) ($page['judul'] ?? ucfirst($slug)),
            'post_name' => $existing > 0 ? (string) get_post_field('post_name', $existing) : $slug,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => siteflow_page_markup($slug, $media_urls, $page_ids),
            'meta_input' => ['_siteflow_page_key' => siteflow_page_key($slug)],
        ];

        if ($elementor !== []) {
            $elementor_json = (string) wp_json_encode($elementor);
            $data['meta_input']['_elementor_edit_mode'] = 'builder';
            $data['meta_input']['_elementor_template_type'] = 'wp-page';
            $data['meta_input']['_elementor_data'] = $elementor_json;
            $data['meta_input']['_siteflow_elementor_hash'] = md5($elementor_json);

            if (defined('ELEMENTOR_VERSION')) {
                $data['meta_input']['_elementor_version'] = ELEMENTOR_VERSION;
            }
        }

        if ($existing > 0) {
            $data['ID'] = $existing;
            $page_id = wp_update_post(wp_slash($data));
        } else {
            $page_id = wp_insert_post(wp_slash($data));
        }

        if (!$page_id || is_wp_error($page_id)) {
            continue;
        }

        $page_ids[$slug] = (int) $page_id;
        $written++;
    }

    if ($page_ids) {
        foreach ($page_ids as $slug => $page_id) {
            if (isset($preserved[$slug])) {
                continue;
            }

            $post = get_post($page_id);

            if (!$post) {
                continue;
            }

            $markup = siteflow_page_markup($slug, $media_urls, $page_ids);

            if ($markup !== '' && $markup !== $post->post_content) {
                wp_update_post(wp_slash(['ID' => $page_id, 'post_content' => $markup]));
            }

            // Lintasan kedua: semua alamat halaman sudah ada, token tautan
            // diganti dengan permalink sungguhan.
            $elementor = siteflow_elementor_payload($slug, $media_urls, $page_ids);
            $elementor_json = $elementor !== [] ? (string) wp_json_encode($elementor) : '';

            if ($elementor_json !== '' && $elementor_json !== (string) get_post_meta($page_id, '_elementor_data', true)) {
                update_post_meta($page_id, '_elementor_data', wp_slash($elementor_json));
                update_post_meta($page_id, '_siteflow_elementor_hash', md5($elementor_json));
                update_post_meta($page_id, '_elementor_edit_mode', 'builder');
                update_post_meta($page_id, '_elementor_template_type', 'wp-page');

                if (defined('ELEMENTOR_VERSION')) {
                    update_post_meta($page_id, '_elementor_version', ELEMENTOR_VERSION);
                }
            }
        }

        update_option('siteflow_page_ids', $page_ids);
        siteflow_import_menu($page_ids);
        siteflow_sync_elementor_kit();

        // Beranda adalah Page sungguhan dan dipasang sebagai halaman depan.
        if (isset($page_ids['beranda']) && (int) $page_ids['beranda'] > 0) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', (int) $page_ids['beranda']);
        }

        if (class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->files_manager)
            && method_exists(\Elementor\Plugin::$instance->files_manager, 'clear_cache')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    update_option('siteflow_import_skipped', count($preserved));

    $version = isset($meta['versi']) && is_string($meta['versi']) ? $meta['versi'] : '';

    if ($version !== '' && $page_ids !== []) {
        update_option('siteflow_content_version', $version);
    }

    return $written;
}

// Satu pintu impor: hanya admin, dan hanya satu permintaan pada satu waktu
// (dua tab wp-admin bersamaan tidak boleh membuat halaman atau lampiran dobel).
function siteflow_run_import(): int
{
    if (!current_user_can('manage_options')) {
        return 0;
    }

    $locked_at = (int) get_option('siteflow_import_lock');

    if ($locked_at > 0 && (time() - $locked_at) < 5 * MINUTE_IN_SECONDS) {
        return 0;
    }

    if ($locked_at > 0) {
        delete_option('siteflow_import_lock');
    }

    if (!add_option('siteflow_import_lock', time())) {
        return 0;
    }

    try {
        return siteflow_import_pages();
    } finally {
        delete_option('siteflow_import_lock');
    }
}

// Versi isi membandingkan tema ini dengan yang pernah diimpor: tema hasil
// build ulang membawa versi baru, jadi halaman diperbarui, bukan tertahan.
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    $meta = siteflow_theme_meta();
    $version = isset($meta['versi']) && is_string($meta['versi']) ? $meta['versi'] : '';

    if ($version !== '' && get_option('siteflow_content_version') !== $version) {
        siteflow_run_import();
    }

    if (isset($_GET['siteflow_reimport']) && check_admin_referer('siteflow_reimport')) {
        $count = siteflow_run_import();
        $skipped = (int) get_option('siteflow_import_skipped', 0);

        add_action('admin_notices', function () use ($count, $skipped) {
            if ($count > 0) {
                $message = sprintf('SiteFlow: %d halaman berhasil dibuat atau diperbarui.', $count);
            } elseif ($skipped > 0) {
                $message = 'SiteFlow: halaman yang sudah disunting lewat Elementor dibiarkan apa adanya.';
            } else {
                $message = 'SiteFlow: tidak ada halaman yang berhasil dibuat. Cek error log server.';
            }

            if ($count > 0 && $skipped > 0) {
                $message .= sprintf(' %d halaman tidak diperbarui karena sudah disunting lewat Elementor.', $skipped);
            }

            echo '<div class="notice notice-' . ($count > 0 || $skipped > 0 ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }
});

// Pintu manual, untuk situs yang sempat memakai build tema lama.
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    $url = wp_nonce_url(add_query_arg('siteflow_reimport', '1'), 'siteflow_reimport');
    echo '<div class="notice notice-info"><p><strong>SiteFlow</strong> - kalau halaman atau menu belum muncul, klik: <a href="' . esc_url($url) . '">Buat/perbarui halaman sekarang</a>.</p></div>';
});

PHP;
    }

    private function writeTemplateFiles(string $html, string $themeDir): void
    {
        $headerMarkup = '';
        $footerMarkup = '';
        $mainMarkup = $html;

        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $bodyMatches)) {
            $mainMarkup = $bodyMatches[1];
        }

        if (preg_match('/<header\b[^>]*>.*?<\/header>/is', $mainMarkup, $headerMatches)) {
            $headerMarkup = $headerMatches[0];
            $mainMarkup = str_replace($headerMarkup, '', $mainMarkup);
        }

        if (preg_match('/<footer\b[^>]*>.*?<\/footer>/is', $mainMarkup, $footerMatches)) {
            $footerMarkup = $footerMatches[0];
            $mainMarkup = str_replace($footerMarkup, '', $mainMarkup);
        }

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

        $footerFile = <<<PHP
{$footerMarkup}
<?php wp_footer(); ?>
</body>
</html>

PHP;
        file_put_contents($themeDir . '/footer.php', trim($footerFile) . "\n");

        // Beranda adalah Page WordPress (Settings > Reading), bukan templat
        // tema. Sisa front-page.php dari build lama akan menimpanya.
        if (is_file($themeDir . '/front-page.php')) {
            unlink($themeDir . '/front-page.php');
        }

        $pageFile = <<<'PHP'
<?php get_header(); ?>

<main class="siteflow-page">
<?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
</main>

<?php get_footer(); ?>

PHP;
        file_put_contents($themeDir . '/page.php', trim($pageFile) . "\n");

        $indexFile = <<<'PHP'
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

        file_put_contents($themeDir . '/README.txt', $this->readmeText());
    }

    private function readmeText(): string
    {
        return <<<'TEXT'
TEMA SITEFLOW
=============

Langkah 1 - Pasang Elementor
Buka Plugins > Add New, cari "Elementor", klik Install, lalu Activate.
Versi gratis sudah cukup untuk menyunting isi halaman secara visual.

Langkah 2 - Aktifkan tema
Buka Appearance > Themes, pilih tema ini, klik Activate.

Begitu admin pertama membuka wp-admin, tema membuat:
- halaman Beranda dan halaman isi lain (menu Pages),
- isi setiap halaman sebagai elemen Elementor (judul, teks, foto, tombol),
- Menu Utama (menu Appearance > Menus),
- foto-foto mockup di Media.

Langkah 3 - Periksa hasilnya
- Pages: halaman berstatus Published. Beranda sudah dipasang sebagai
  halaman depan (Settings > Reading > Your homepage displays).
- Halaman: klik Edit with Elementor. Setiap bagian bisa dipilih dan diubah
  langsung di kanvas; tidak ada kode HTML di dalamnya.
- Appearance > Menus: "Menu Utama" terpasang di lokasi "Menu Utama";
  menambah halaman di sini langsung mengubah navigasi situs.
- Media: foto dari mockup sudah terunggah; foto bisa diganti kapan saja.

Langkah 4 - Mengubah isi
Buka Pages, klik halaman, klik Edit with Elementor. Ubah teks, foto, atau
tombol, lalu klik Update.

Kalau halaman atau menu belum muncul
Buka sembarang halaman di wp-admin, lalu cek lagi. Ada juga notifikasi
dengan tombol "Buat/perbarui halaman sekarang" di atas wp-admin.

Catatan
- Header dan footer adalah bagian tema, bukan halaman Elementor. Mengubah
  menu lewat Appearance > Menus; mengubah gaya lewat Appearance > Customize.
- Suntingan Elementor tidak ditimpa saat tema dipasang ulang. Hanya halaman
  yang belum pernah disunting yang ikut diperbarui.
- Kalau Elementor tidak aktif, halaman tetap tampil dari cadangan HTML dan
  bisa disunting lewat Block Editor.
- Tidak ada skrip, formulir, iframe, atau alamat luar di tema ini.
TEXT;
    }

    /**
     * @param array<string, string> $pageMarkup
     * @param array<string, string> $pageTitles
     * @param array<string, list<array<string, mixed>>> $elementorData
     * @param list<array{slug: string, label: string}> $navItems
     * @param array<string, string> $imageFiles
     * @param array<string, string> $imageAlts
     */
    private function writeContent(
        string $themeDir,
        Project $project,
        array $pageMarkup,
        array $pageTitles,
        array $elementorData,
        array $navItems,
        array $imageFiles,
        array $imageAlts
    ): void {
        $contentDir = $themeDir . '/content';

        if (!is_dir($contentDir)) {
            mkdir($contentDir, 0775, true);
        }

        $pages = [];
        $versionSource = '';
        $imageList = array_values($imageFiles);

        foreach ($pageMarkup as $slug => $markup) {
            $markup = trim($markup);
            $elementor = $elementorData[$slug] ?? [];

            file_put_contents($contentDir . '/' . $slug . '.html', $markup . "\n");

            $pageVersion = md5(
                $slug . "\0" . $markup . "\0"
                . json_encode($elementor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\0"
                . implode("\0", $imageList) . "\0"
                . json_encode($imageAlts)
            );

            $payloadJson = (string) json_encode(
                ['versi' => $pageVersion, 'elementor' => $elementor],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            if (strlen($payloadJson) > 110 * 1024) {
                $payloadJson = (string) json_encode(
                    ['versi' => $pageVersion, 'elementor' => $elementor],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            }

            file_put_contents(
                $contentDir . '/' . $slug . '.json',
                $payloadJson . "\n"
            );

            $pages[] = [
                'slug' => $slug,
                'judul' => $pageTitles[$slug] ?? ucfirst($slug),
            ];
            $versionSource .= $slug . "\0" . $pageVersion . "\0";
        }

        $versionSource .= implode("\0", $imageList) . "\0";
        $versionSource .= json_encode($navItems);

        $meta = [
            'versi' => md5($versionSource),
            'kunci' => $this->textDomain($project) . '-' . $project->id,
            'halaman' => $pages,
            'nav' => $navItems,
            'gambar' => $imageList,
            'gambar_alt' => (object) array_filter(
                $imageAlts,
                static fn (string $alt): bool => trim($alt) !== ''
            ),
        ];

        file_put_contents(
            $contentDir . '/halaman.json',
            (string) json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }

    private function textDomain(Project $project): string
    {
        return Str::slug($project->name) ?: 'siteflow-theme';
    }

    /**
     * Memastikan tidak ada penanda __EXITO_ yang tertinggal di seluruh berkas
     * tema, dan token isi halaman hanya yang dikenal importer.
     */
    private function assertNoRemainingMarkers(string $themeDir): void
    {
        $files = array_merge(
            glob($themeDir . '/*.php') ?: [],
            glob($themeDir . '/*.css') ?: [],
            glob($themeDir . '/content/*.html') ?: [],
            glob($themeDir . '/content/*.json') ?: []
        );

        foreach ($files as $filePath) {
            $content = (string) file_get_contents($filePath);

            if (str_contains($content, '__EXITO_')) {
                throw new RuntimeException(
                    'Masih terdapat penanda __EXITO_ yang belum terganti pada berkas: ' . basename($filePath)
                );
            }

            if (preg_match_all(self::CONTENT_TOKEN_PATTERN, $content, $tokens) > 0) {
                foreach ($tokens[1] as $index => $kind) {
                    if (!in_array($kind, ['gambar', 'halaman'], true)) {
                        throw new RuntimeException('Token isi halaman tidak dikenal: ' . $tokens[0][$index]);
                    }
                }
            }
        }
    }
}
