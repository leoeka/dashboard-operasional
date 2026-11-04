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

    private const GLOBAL_SELECTORS = ['html', 'body', ':root', '*', 'main'];

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

        if ($buttonClasses !== []) {
            foreach (self::SCOPES as $scope) {
                $output[] = $scope . ' .elementor-widget-button .elementor-button{font-family:inherit;font-size:inherit;line-height:inherit;font-weight:inherit;letter-spacing:inherit;text-transform:none;text-align:inherit;border-radius:0}';
                $output[] = $scope . ' .siteflow-inline-link .elementor-widget-container>p{margin:0}';
            }
        } else {
            foreach (self::SCOPES as $scope) {
                $output[] = $scope . ' .siteflow-inline-link .elementor-widget-container>p{margin:0}';
            }
        }

        foreach ($rules as $rule) {
            $output[] = $rule;
        }

        return implode("\n", $output);
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

            foreach ($this->splitSelectorList($prelude) as $selector) {
                $selector = trim($selector);

                if ($selector === '') {
                    continue;
                }

                if ($this->isRootSelector($selector)) {
                    $root[] = $selector;
                } else {
                    $local[] = $scope . ' ' . $selector;
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

    private function isRootSelector(string $selector): bool
    {
        return preg_match('/^(?::root|html|body|\*)(?![\w-])/i', $selector) === 1;
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
            if (!$this->hasClass($selector, $class)) {
                continue;
            }

            // Kelas tombol kandidat berpindah ke <a class="elementor-button">.
            // Satu kelas pengganti menjaga bobot selektor aslinya, jadi
            // suntingan klien pada widget tombol tetap menang.
            $replaced = (string) preg_replace(
                '/(^|[\s>+~])\.' . preg_quote($class, '/') . '(?![\w-])/',
                '$1.elementor-button',
                $selector
            );

            if ($replaced !== $selector) {
                $base[] = $replaced;
            }
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
     * Gambar tema lazim mengisi kontainer bertinggi tetap (height:100%). Di
     * Elementor, <img> bukan lagi anak langsung kontainer karena ada pembungkus
     * widget, sehingga persentase tinggi tidak terdefinisi dan gambar mengecil.
     * Pembungkusnya diberi tinggi 100% supaya persentase gambar tetap dihitung
     * terhadap kontainer aslinya.
     *
     * @return list<string>
     */
    private function imageWrapperVariants(string $selector, string $body): array
    {
        if (preg_match('/(?<![-\w])height\s*:\s*[0-9.]+%/i', $body) !== 1) {
            return [];
        }

        if (preg_match('/(?:^|[\s>+~])img$/', $selector) !== 1) {
            return [];
        }

        $parent = (string) preg_replace('/(?:\s+|>)\s*img$/', '', $selector);

        return [
            $parent . '>.elementor-widget',
            $parent . '>.elementor-widget>.elementor-widget-container',
        ];
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
