<?php

namespace App\Services;

/**
 * Menyalin aturan CSS kandidat ke dalam pembungkus Elementor.
 *
 * Elementor memuat CSS-nya sesudah style.css tema dan memberi gaya sendiri
 * pada kontainer (.e-con), judul (.elementor-heading-title), gambar, dan
 * tombol. Tanpa salinan berkekhususan lebih tinggi, padding kontainer, margin
 * judul, dan warna tombol mockup kalah. Setiap aturan disalin dua kali: di
 * bawah .siteflow-page (halaman depan) dan .elementor-edit-area (kanvas
 * penyunting), dengan kelas cakupan disebut dua kali sehingga bobotnya naik
 * dua tingkat.
 *
 * Angka itu dipilih, bukan asal tinggi. Gaya bawaan Elementor pada judul dan
 * teks berbobot dua kelas; CSS per-elemen kontainer berbobot tiga kelas dan
 * dimuat sesudah tema. Cakupan ganda menghasilkan tiga kelas: menang atas
 * bawaan, dan seri dengan CSS per-elemen kontainer sehingga suntingan klien
 * tetap menang. Gaya per-elemen widget berbobot empat kelas, jadi suntingan
 * klien pada judul, gambar, dan tombol juga tetap menang.
 *
 * Tiga penyesuaian struktur:
 * - Kontainer Elementor membungkus widget dengan .elementor-widget dan
 *   .elementor-widget-container; pemilih anak langsung (>) diberi varian
 *   yang menyisipkan dua pembungkus itu.
 * - Kelas gambar berpindah ke pembungkus widget; aturannya diberi varian
 *   yang berakhir pada img.
 * - Kelas judul berpindah ke pembungkus widget; aturannya diberi varian
 *   yang berakhir pada .elementor-heading-title.
 * - Kelas tombol menjadi .elementor-button di dalam widget tombol.
 */
class ElementorCssBridge
{
    private const SCOPES = [
        '.siteflow-page.siteflow-page',
        '.elementor-edit-area.elementor-edit-area',
    ];

    /**
     * Lingkup satu kelas untuk aturan yang harus menang atas CSS bawaan
     * Elementor (bobot dua kelas) tapi tetap kalah dari salinan jembatan
     * (bobot tiga kelas) supaya warna yang ditulis markah tidak ditimpa.
     */
    private const SOFT_SCOPES = [
        '.siteflow-page',
        '.elementor-edit-area',
    ];

    private const GLOBAL_SELECTORS = ['html', 'body', ':root', '*', 'main'];

    /**
     * Setelan awal Elementor yang harus dibatalkan sebelum gaya mockup dipakai.
     *
     * Ditulis sebagai metode tersendiri, bukan di dalam build(), karena
     * stylesheet digabung dari dua sumber CSS (beranda dan halaman isi).
     * Bobot setelan ini sama dengan bobot aturan tombol hasil jembatan, jadi
     * kalau ia tersalin pada penggabungan kedua ia jatuh setelah aturan
     * tombol beranda dan menang: radius serta huruf tombol kembali ke bawaan.
     *
     * @param list<string> $buttonClasses kelas tautan yang jadi widget tombol
     */
    public function resetRules(array $buttonClasses = []): string
    {
        $output = [];

        foreach (self::SCOPES as $scope) {
            if ($buttonClasses !== []) {
                $output[] = $scope . ' .elementor-widget-button .elementor-button{font-family:inherit;font-size:inherit;line-height:inherit;font-weight:inherit;letter-spacing:inherit;text-transform:none;text-align:inherit;border-radius:0}';
            }
            $output[] = $scope . ' .siteflow-inline-link .elementor-widget-container>p{margin:0}';
        }

        return implode("\n", $output);
    }

    /**
     * @param list<string> $buttonClasses kelas tautan yang jadi widget tombol
     * @param list<string> $imageClasses kelas <img> kandidat
     * @param list<string> $headingClasses kelas judul kandidat
     */
    public function build(
        string $css,
        array $buttonClasses = [],
        array $imageClasses = [],
        array $headingClasses = []
    ): string {
        $rules = $this->scopedRules($css, $buttonClasses, $imageClasses, $headingClasses);

        if ($rules === []) {
            return '';
        }

        $output = [];
        $output[] = '/* Jembatan Elementor: salinan aturan di atas dengan kekhususan lebih tinggi,';
        $output[] = '   supaya gaya mockup tidak kalah oleh CSS bawaan Elementor. */';

        foreach ($rules as $rule) {
            $output[] = $rule;
        }

        return implode("\n", $output);
    }

    /**
     * Kembalikan warna yang di mockup diwarisi, tapi direbut Elementor.
     *
     * Pita gelap menulis warnanya pada kontainer (.hero{color:white}) dan
     * judul di dalamnya tidak punya warna sendiri, jadi judul ikut putih.
     * Di WordPress, CSS per-halaman Elementor menempelkan warna kit pada
     * .elementor-heading-title (bobot dua kelas), dan warisan itu kalah.
     *
     * Aturan di sini berbobot tiga kelas: cukup untuk mengalahkan kit, masih
     * di bawah salinan jembatan (empat kelas) sehingga warna yang memang
     * ditulis di markah tetap menang. Yang dianggap pita hanya kelas yang
     * menulis warna DAN latar; kelas tombol tidak dihitung karena tidak
     * pernah memuat judul.
     *
     * @param list<string> $skipClasses kelas tombol, bukan permukaan pita
     */
    public function colourInheritanceRules(string $css, array $skipClasses = []): string
    {
        $bands = [];

        foreach ($this->parseBlocks($css) as $block) {
            $prelude = trim($block['prelude']);

            if ($prelude === '' || $prelude[0] === '@') {
                continue;
            }

            if (preg_match('/(?<![-\w])color\s*:/i', $block['body']) !== 1) {
                continue;
            }

            if (preg_match('/(?<![-\w])background(-color|-image)?\s*:/i', $block['body']) !== 1) {
                continue;
            }

            foreach ($this->splitSelectorList($prelude) as $selector) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $selector));

                // Hanya pita yang berdiri sendiri (.hero, .coverage). Pemilih
                // bersusun sudah menyebut induknya sendiri.
                if (preg_match('/^\.[a-zA-Z][\w-]*(?:\.[a-zA-Z][\w-]*)*$/', $selector) !== 1) {
                    continue;
                }

                if (array_intersect(explode('.', $selector), $skipClasses) !== []) {
                    continue;
                }

                $bands[$selector] = true;
            }
        }

        if ($bands === []) {
            return '';
        }

        $selectors = [];

        foreach (self::SOFT_SCOPES as $scope) {
            foreach (array_keys($bands) as $band) {
                foreach (['.elementor-heading-title', '.elementor-widget-text-editor'] as $target) {
                    $selectors[] = $scope . ' ' . $band . ' ' . $target;
                }
            }
        }

        return "/* Warna pita: judul dan blok teks tidak punya warna sendiri di mockup,\n"
            . "   jadi keduanya mewarisi lagi dari pita. */\n"
            . implode(',', $selectors) . "{color:inherit}\n";
    }

    /**
     * Menyempitkan CSS satu halaman isi ke pembungkus halamannya.
     *
     * Semua halaman isi dulu digabung ke satu stylesheet; aturan halaman isi
     * yang ditulis belakangan menimpa beranda. Pemanggil membungkus markah
     * tiap halaman dengan kelas lingkup, lalu meminta CSS-nya diberi awalan
     * kelas itu. Aturan akar (:root, html, body, *) dan at-rule global
     * (font-face, keyframes, import) dikembalikan terpisah supaya pemanggil
     * memutuskan mana yang dipakai.
     *
     * @return array{at: string, global: string, scoped: string}
     */
    public function scopeCss(string $css, string $scope): array
    {
        $at = [];
        $global = [];
        $scoped = [];

        $this->collectScoped($this->parseBlocks($css), $scope, $at, $global, $scoped);

        return [
            'at' => implode("\n", $at),
            'global' => implode("\n", $global),
            'scoped' => implode("\n", $scoped),
        ];
    }

    /**
     * @param list<array{prelude: string, body: string}> $blocks
     * @param list<string> $at
     * @param list<string> $global
     * @param list<string> $scoped
     */
    private function collectScoped(array $blocks, string $scope, array &$at, array &$global, array &$scoped): void
    {
        foreach ($blocks as $block) {
            $prelude = trim($block['prelude']);

            if ($prelude === '') {
                continue;
            }

            if ($prelude[0] === '@') {
                if ($this->isNestedAtRule($prelude) && str_contains($block['body'], '{')) {
                    $innerAt = [];
                    $innerGlobal = [];
                    $innerScoped = [];
                    $this->collectScoped($this->parseBlocks($block['body']), $scope, $innerAt, $innerGlobal, $innerScoped);

                    if ($innerScoped !== [] || $innerAt !== []) {
                        $scoped[] = $prelude . '{' . implode('', array_merge($innerAt, $innerScoped)) . '}';
                    }

                    if ($innerGlobal !== []) {
                        $global[] = $prelude . '{' . implode('', $innerGlobal) . '}';
                    }

                    continue;
                }

                if (str_ends_with($prelude, ';') && trim($block['body']) === '') {
                    $at[] = $prelude;
                    continue;
                }

                $at[] = $prelude . '{' . $block['body'] . '}';
                continue;
            }

            $local = [];
            $root = [];
            $lingkup = $this->splitSelectorList($scope);

            foreach ($this->splitSelectorList($prelude) as $selector) {
                $selector = trim($selector);

                if ($selector === '') {
                    continue;
                }

                if ($this->isRootSelector($selector)) {
                    $root[] = $selector;
                    continue;
                }

                // Setelan awal tanpa kelas sudah dibawa beranda dan berlaku
                // untuk seluruh situs. Diberi awalan lingkup halaman, ia ikut
                // membawa kelas lingkup dan mengalahkan aturan kelas di
                // halaman yang sama (a{color:inherit} menelan .button).
                if ($this->isElementReset($selector)) {
                    continue;
                }

                // Satu aturan bisa dipakai beberapa halaman sekaligus, jadi
                // tiap lingkup harus dikawinkan dengan tiap pemilih. Menempelkan
                // daftar lingkup apa adanya hanya menyambung pemilih ke lingkup
                // terakhir, dan halaman lainnya kehilangan aturannya.
                foreach ($lingkup as $satu) {
                    $local[] = $satu . ' ' . $selector;
                }
            }

            if ($root !== []) {
                $global[] = implode(',', $root) . '{' . trim($block['body']) . '}';
            }

            if ($local !== []) {
                $scoped[] = implode(',', $local) . '{' . trim($block['body']) . '}';
            }
        }
    }

    /**
     * Buang aturan yang kelasnya tidak muncul di markah halaman.
     *
     * Tiap halaman kandidat membawa salinan seluruh stylesheet situs, termasuk
     * aturan bilah atas dan kaki. Tema merender keduanya dari templat sendiri,
     * jadi salinan berlingkup halaman isi tidak akan pernah cocok dan hanya
     * menambah berat style.css.
     *
     * @param list<string> $usedClasses
     */
    public function pruneUnusedClasses(string $css, array $usedClasses): string
    {
        return $this->pruneBlocks($this->parseBlocks($css), array_fill_keys($usedClasses, true));
    }

    /**
     * @param list<array{prelude: string, body: string}> $blocks
     * @param array<string, true> $used
     */
    private function pruneBlocks(array $blocks, array $used): string
    {
        $output = [];

        foreach ($blocks as $block) {
            $prelude = trim($block['prelude']);

            if ($prelude === '') {
                continue;
            }

            if ($prelude[0] === '@') {
                if ($this->isNestedAtRule($prelude) && str_contains($block['body'], '{')) {
                    $inner = $this->pruneBlocks($this->parseBlocks($block['body']), $used);

                    if ($inner !== '') {
                        $output[] = $prelude . '{' . $inner . '}';
                    }

                    continue;
                }

                $output[] = $prelude . (trim($block['body']) === '' ? '' : '{' . $block['body'] . '}');
                continue;
            }

            // Daftar pemilih dipangkas per pemilih, bukan per aturan. Kalau
            // tidak, satu pemilih yang kelasnya tidak muncul di halaman ini
            // ikut membuang pemilih lain yang masih hidup: aturan
            // ".nav-cta, .button{color:white}" hilang seluruhnya dari halaman
            // isi karena .nav-cta hanya ada di kepala situs, padahal tombol di
            // dalam halaman inilah yang memakai .button.
            $pemilih = $this->splitSelectorList($prelude);
            $hidup = array_values(array_filter(
                $pemilih,
                fn (string $satu): bool => $this->selectorUsesKnownClasses($satu, $used)
            ));

            if ($hidup !== []) {
                $output[] = implode(',', $hidup) . '{' . trim($block['body']) . '}';
            }
        }

        return implode("\n", $output);
    }

    /**
     * Selector dipertahankan kalau setiap kelas yang disebutnya ada di markah.
     * Selector tanpa kelas (h1, p, *) selalu dipertahankan.
     *
     * @param array<string, true> $used
     */
    private function selectorUsesKnownClasses(string $prelude, array $used): bool
    {
        preg_match_all('/\.([a-zA-Z_][a-zA-Z0-9_-]*)/', $prelude, $matches);

        foreach ($matches[1] as $class) {
            if (!isset($used[$class])) {
                return false;
            }
        }

        return true;
    }

    private function isRootSelector(string $selector): bool
    {
        return preg_match('/^(?::root|html|body|\*)(?![\w-])/i', $selector) === 1;
    }

    /**
     * Setelan awal stylesheet kandidat: pemilih yang hanya menyebut nama
     * elemen (a, p, img, ul li) tanpa kelas, id, atau atribut.
     */
    private function isElementReset(string $selector): bool
    {
        return preg_match('/[.#\[]/', $selector) !== 1;
    }

    /**
     * Kelas yang di CSS aslinya menjadi kontainer sebaris (display:flex/grid).
     * Elemen dengan kelas ini harus diarahkan ke flex-direction row, karena
     * kontainer Elementor menumpuk isi ke bawah secara bawaan.
     *
     * @return list<string>
     */
    public function displayContainerClasses(string $css): array
    {
        return $this->collectDisplayClasses($this->parseBlocks($css));
    }

    /**
     * @param list<array{prelude: string, body: string}> $blocks
     * @return list<string>
     */
    private function collectDisplayClasses(array $blocks): array
    {
        $classes = [];

        foreach ($blocks as $block) {
            $prelude = trim($block['prelude']);

            if ($prelude === '') {
                continue;
            }

            if ($prelude[0] === '@') {
                if ($this->isNestedAtRule($prelude) && str_contains($block['body'], '{')) {
                    foreach ($this->collectDisplayClasses($this->parseBlocks($block['body'])) as $class) {
                        $classes[] = $class;
                    }
                }

                continue;
            }

            if (preg_match('/(?<![-\w])display\s*:\s*(?:inline-)?(?:flex|grid)\b/i', $block['body']) !== 1) {
                continue;
            }

            foreach ($this->splitSelectorList($prelude) as $selector) {
                $compounds = preg_split('/[\s>+~]+/', trim((string) preg_replace('/\s*>\s*/', '>', $selector))) ?: [];
                $last = (string) preg_replace('/\[[^\]]*\]/', '', (string) end($compounds));

                if (preg_match_all('/\.(-?[_a-zA-Z][\w-]*)/', $last, $matches) > 0) {
                    foreach ($matches[1] as $class) {
                        $classes[] = $class;
                    }
                }
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @param list<string> $buttonClasses
     * @param list<string> $imageClasses
     * @param list<string> $headingClasses
     * @return list<string>
     */
    private function scopedRules(
        string $css,
        array $buttonClasses,
        array $imageClasses,
        array $headingClasses
    ): array {
        $output = [];

        foreach ($this->parseBlocks($css) as $block) {
            $prelude = trim($block['prelude']);

            if ($prelude === '') {
                continue;
            }

            if ($prelude[0] === '@') {
                if ($this->isNestedAtRule($prelude) && str_contains($block['body'], '{')) {
                    $inner = $this->scopedRules($block['body'], $buttonClasses, $imageClasses, $headingClasses);

                    if ($inner !== []) {
                        $output[] = $prelude . '{' . implode('', $inner) . '}';
                    }

                    continue;
                }

                // @import berakhir dengan titik koma dan tidak punya isi.
                if (str_ends_with($prelude, ';') && trim($block['body']) === '') {
                    $output[] = $prelude;
                    continue;
                }

                // @font-face, @keyframes: bukan aturan tampilan konten.
                $output[] = $prelude . '{' . $block['body'] . '}';
                continue;
            }

            $selectors = [];

            foreach ($this->splitSelectorList($prelude) as $selector) {
                foreach ($this->selectorVariants($selector, $buttonClasses, $imageClasses, $headingClasses, $block['body']) as $variant) {
                    $selectors[] = $variant;
                }
            }

            $selectors = array_values(array_unique($selectors));

            if ($selectors !== []) {
                $output[] = implode(',', $selectors) . '{' . trim($block['body']) . '}';
            }
        }

        return $output;
    }

    /**
     * @param list<string> $buttonClasses
     * @param list<string> $imageClasses
     * @param list<string> $headingClasses
     * @return list<string>
     */
    private function selectorVariants(
        string $selector,
        array $buttonClasses,
        array $imageClasses,
        array $headingClasses,
        string $body = ''
    ): array {
        $selector = trim((string) preg_replace('/\s+/', ' ', $selector));

        if ($selector === '') {
            return [];
        }

        $selector = (string) preg_replace('/\s*>\s*/', '>', $selector);
        $first = strtolower((string) preg_split('/[\s>+~]/', $selector)[0]);

        if (in_array($first, self::GLOBAL_SELECTORS, true)) {
            return [];
        }

        $base = [$selector];

        foreach ($this->childCombinators($selector) as $position) {
            $base[] = substr($selector, 0, $position + 1)
                . '.elementor-widget>.elementor-widget-container>'
                . substr($selector, $position + 1);
        }

        foreach ($imageClasses as $class) {
            if ($this->hasClass($selector, $class)) {
                $base[] = $selector . ' img';
            }
        }

        foreach ($this->imageWrapperVariants($selector, $body) as $wrapper) {
            $base[] = $wrapper;
        }

        foreach ($headingClasses as $class) {
            if ($this->hasClass($selector, $class)) {
                $base[] = $selector . ' .elementor-heading-title';
            }
        }

        foreach ($buttonClasses as $class) {
            // Kelas sumber tinggal pada pembungkus widget tombol, anchor-nya
            // tetap .elementor-button, jadi aturannya perlu varian turun-tingkat.
            // Tanpa varian itu .button dan .button-light menimpa pemilih yang
            // sama dan warna tombol terakhir yang menang di seluruh halaman.
            if (!$this->endsWithClass($selector, $class)) {
                continue;
            }

            $base[] = $selector . ' .elementor-button';
        }

        $scoped = [];

        foreach (array_unique($base) as $variant) {
            foreach (self::SCOPES as $scope) {
                $scoped[] = $scope . ' ' . $variant;
            }
        }

        return $scoped;
    }

    /**
     * Gambar dan seni latar tema lazim mengisi kontainer bertinggi tetap
     * (height:100%). Di Elementor, img/svg bukan lagi anak langsung kontainer
     * karena ada pembungkus widget, sehingga persentase tinggi tidak terdefinisi
     * dan gambar mengecil sampai nol. Pembungkusnya diberi ukuran penuh supaya
     * persentase anak dihitung terhadap kontainer aslinya.
     *
     * @return list<string>
     */
    private function imageWrapperVariants(string $selector, string $body): array
    {
        if (preg_match('/(?<![-\w])height\s*:\s*[0-9.]+%/i', $body) !== 1) {
            return [];
        }

        if (preg_match('/(?:^|[\s>+~])(?:img|svg)$/', $selector) !== 1) {
            return [];
        }

        $parent = (string) preg_replace('/(?:\s+|>)\s*(?:img|svg)$/', '', $selector);

        return [
            $parent . '>.elementor-widget',
            $parent . '>.elementor-widget>.elementor-widget-container',
        ];
    }

    /**
     * Kelas disebut pada bagian terakhir pemilih (.hero .button, .button:hover),
     * bukan di tengah (.button svg).
     */
    private function endsWithClass(string $selector, string $class): bool
    {
        $parts = preg_split('/[\s>+~]+/', trim($selector)) ?: [];

        return (bool) preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', (string) end($parts));
    }

    private function hasClass(string $selector, string $class): bool
    {
        return preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', $selector) === 1;
    }

    /**
     * @return list<int>
     */
    private function childCombinators(string $selector): array
    {
        $positions = [];
        $length = strlen($selector);
        $quote = '';
        $brackets = 0;
        $parens = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];

            if ($quote !== '') {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = '';
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $brackets++;
                continue;
            }

            if ($char === ']') {
                $brackets = max(0, $brackets - 1);
                continue;
            }

            if ($char === '(') {
                $parens++;
                continue;
            }

            if ($char === ')') {
                $parens = max(0, $parens - 1);
                continue;
            }

            if ($char === '>' && $brackets === 0 && $parens === 0) {
                $positions[] = $i;
            }
        }

        return $positions;
    }

    private function isNestedAtRule(string $prelude): bool
    {
        return preg_match('/^@(media|supports|layer|container)\b/i', $prelude) === 1;
    }

    /**
     * @return list<string>
     */
    private function splitSelectorList(string $selectors): array
    {
        $parts = [];
        $current = '';
        $length = strlen($selectors);
        $quote = '';
        $brackets = 0;
        $parens = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $selectors[$i];

            if ($quote !== '') {
                $current .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $selectors[++$i];
                } elseif ($char === $quote) {
                    $quote = '';
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === '[') {
                $brackets++;
            } elseif ($char === ']') {
                $brackets = max(0, $brackets - 1);
            } elseif ($char === '(') {
                $parens++;
            } elseif ($char === ')') {
                $parens = max(0, $parens - 1);
            }

            if ($char === ',' && $brackets === 0 && $parens === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Membaca CSS menjadi blok prelude/body; kurawal di dalam string dan
     * kurung tidak dihitung.
     *
     * @return list<array{prelude: string, body: string}>
     */
    private function parseBlocks(string $css): array
    {
        $blocks = [];
        $length = strlen($css);
        $i = 0;

        while ($i < $length) {
            while ($i < $length && ctype_space($css[$i])) {
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            $start = $i;
            $open = -1;
            $semicolon = -1;
            $quote = '';
            $parens = 0;

            for ($j = $i; $j < $length; $j++) {
                $char = $css[$j];

                if ($quote !== '') {
                    if ($char === '\\') {
                        $j++;
                    } elseif ($char === $quote) {
                        $quote = '';
                    }

                    continue;
                }

                if ($char === '"' || $char === "'") {
                    $quote = $char;
                    continue;
                }

                if ($char === '(') {
                    $parens++;
                    continue;
                }

                if ($char === ')') {
                    $parens = max(0, $parens - 1);
                    continue;
                }

                if ($parens > 0) {
                    continue;
                }

                if ($char === '{') {
                    $open = $j;
                    break;
                }

                if ($char === ';') {
                    $semicolon = $j;
                    break;
                }
            }

            if ($open < 0) {
                if ($semicolon >= 0) {
                    $blocks[] = ['prelude' => trim(substr($css, $start, $semicolon - $start)) . ';', 'body' => ''];
                    $i = $semicolon + 1;
                    continue;
                }

                break;
            }

            $depth = 1;
            $quote = '';

            for ($k = $open + 1; $k < $length; $k++) {
                $char = $css[$k];

                if ($quote !== '') {
                    if ($char === '\\') {
                        $k++;
                    } elseif ($char === $quote) {
                        $quote = '';
                    }

                    continue;
                }

                if ($char === '"' || $char === "'") {
                    $quote = $char;
                    continue;
                }

                if ($char === '{') {
                    $depth++;
                    continue;
                }

                if ($char === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $blocks[] = [
                            'prelude' => trim(substr($css, $start, $open - $start)),
                            'body' => substr($css, $open + 1, $k - $open - 1),
                        ];
                        $i = $k + 1;
                        break;
                    }
                }
            }

            if ($i <= $open) {
                // Blok tidak tertutup: hentikan, jangan menyalin CSS rusak.
                break;
            }
        }

        return $blocks;
    }
}
