<?php

namespace App\Services;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;
use DOMText;
use DOMXPath;
use RuntimeException;

/**
 * Menerjemahkan isi halaman kandidat (HTML) menjadi data elemen Elementor.
 *
 * Aturan rancangan 2026-11-02 bagian 5: pembungkus menjadi container dengan
 * kelas asli, judul menjadi widget heading, teks menjadi text-editor, foto
 * menjadi image, tautan bergaya tombol menjadi button. Markah yang tidak bisa
 * diterjemahkan (tabel, formulir, iframe) menggagalkan build, bukan diam-diam
 * berubah bentuk.
 */
class ElementorPageMapper
{
    private const CONTAINERS = ['section', 'article', 'div', 'aside', 'header', 'footer', 'nav', 'figure', 'main'];

    private const HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    private const TEXT_BLOCKS = ['p', 'ul', 'ol', 'blockquote', 'pre', 'hr', 'dl', 'address'];

    private const INLINE = [
        'a', 'b', 'strong', 'em', 'i', 'span', 'small', 'code', 'sup', 'sub',
        'mark', 'time', 'abbr', 'u', 's', 'del', 'ins', 'dfn', 'var', 'samp',
        'kbd', 'bdi', 'bdo', 'br', 'wbr', 'q', 'cite',
    ];

    private const UNSUPPORTED = [
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption',
        'form', 'input', 'textarea', 'select', 'option', 'button', 'label',
        'iframe', 'script', 'style', 'object', 'embed', 'video', 'audio',
        'source', 'track', 'canvas', 'map', 'area', 'svg', 'picture',
    ];

    private const HTML_TAGS = ['div', 'section', 'article', 'aside', 'header', 'footer', 'nav'];

    private DOMDocument $doc;

    private string $slug = '';

    private int $counter = 0;

    /** @var list<string> */
    private array $buttons = [];

    /** @var list<string> */
    private array $imageClasses = [];

    /** @var list<string> */
    private array $headingClasses = [];

    /** @var array<string, string> */
    private array $imageAlts = [];

    /** @var array<string, true> */
    private array $flexContainers = [];

    /**
     * @param list<string> $flexContainers kelas yang di CSS aslinya memakai
     *     display:flex/grid; kontainernya harus tetap sebaris seperti aslinya
     * @return array{
     *     elements: list<array<string, mixed>>,
     *     buttons: list<string>,
     *     image_classes: list<string>,
     *     heading_classes: list<string>,
     *     image_alts: array<string, string>
     * }
     */
    public function compile(string $html, string $slug, array $flexContainers = []): array
    {
        $this->slug = $slug;
        $this->counter = 0;
        $this->buttons = [];
        $this->imageClasses = [];
        $this->headingClasses = [];
        $this->imageAlts = [];
        $this->flexContainers = array_fill_keys($flexContainers, true);
        $this->doc = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $this->doc->loadHTML(
            '<?xml encoding="utf-8" ?><div id="siteflow-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (iterator_to_array($this->doc->childNodes) as $node) {
            if ($node instanceof DOMProcessingInstruction) {
                $this->doc->removeChild($node);
            }
        }

        $root = (new DOMXPath($this->doc))->query('//*[@id="siteflow-root"]')->item(0);

        if (!$root instanceof DOMElement) {
            throw new RuntimeException('Isi halaman tidak bisa dibaca saat menyusun elemen Elementor.');
        }

        return [
            'elements' => $this->mapChildren($root, 0),
            'buttons' => array_values(array_unique($this->buttons)),
            'image_classes' => array_values(array_unique($this->imageClasses)),
            'heading_classes' => array_values(array_unique($this->headingClasses)),
            'image_alts' => $this->imageAlts,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapChildren(DOMNode $parent, int $depth): array
    {
        $elements = [];
        $run = '';

        $flush = function () use (&$elements, &$run): void {
            $markup = trim($run);
            $run = '';

            if ($markup === '' || strip_tags($markup) === '') {
                return;
            }

            $elements[] = $this->textEditor($markup);
        };

        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMComment) {
                continue;
            }

            if ($node instanceof DOMText) {
                $run .= (string) $this->doc->saveHTML($node);
                continue;
            }

            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::UNSUPPORTED, true)) {
                throw new RuntimeException(
                    "Markah <{$tag}> tidak bisa diedit lewat Elementor; build dihentikan supaya isi tidak berubah bentuk."
                );
            }

            if ($tag === 'a') {
                $image = $this->linkedImage($node);

                if ($image !== null) {
                    $flush();
                    $elements[] = $image;
                } elseif ($this->isButton($node)) {
                    $flush();
                    $elements[] = $this->button($node);
                } elseif (trim(strip_tags($run)) !== '') {
                    $run .= (string) $this->doc->saveHTML($node);
                } else {
                    // Tautan berdiri sendiri di dalam kontainer: widget sendiri
                    // supaya tata letak flex kontainer tetap mengatur jaraknya.
                    $run = '';
                    $elements[] = $this->inlineLink($node);
                }

                continue;
            }

            if ($tag === 'img') {
                $flush();
                $elements[] = $this->image($node);
                continue;
            }

            if (in_array($tag, self::INLINE, true)) {
                $run .= (string) $this->doc->saveHTML($node);
                continue;
            }

            $flush();

            if ($tag === 'main' && $this->attribute($node, 'id') === '' && $this->attribute($node, 'class') === '') {
                foreach ($this->mapChildren($node, $depth) as $child) {
                    $elements[] = $child;
                }

                continue;
            }

            if (in_array($tag, self::HEADINGS, true)) {
                $elements[] = $this->heading($node);
                continue;
            }

            if (in_array($tag, self::TEXT_BLOCKS, true)) {
                $elements[] = $this->textEditor((string) $this->doc->saveHTML($node));
                continue;
            }

            if (in_array($tag, self::CONTAINERS, true)) {
                $elements[] = $this->container($node, $depth);
                continue;
            }

            throw new RuntimeException(
                "Markah <{$tag}> tidak dikenal penerjemah Elementor; build dihentikan supaya isi tidak berubah bentuk."
            );
        }

        $flush();

        return $elements;
    }

    /**
     * @return array<string, mixed>
     */
    private function container(DOMElement $element, int $depth): array
    {
        $tag = strtolower($element->nodeName);
        $htmlTag = in_array($tag, self::HTML_TAGS, true) ? $tag : 'div';

        return [
            'id' => $this->nextId(),
            'elType' => 'container',
            'isInner' => $depth > 0,
            'settings' => $this->cleanSettings([
                'content_width' => 'full',
                'html_tag' => $htmlTag,
                'flex_direction' => $this->flexDirection($element),
                'flex_gap' => ['column' => '0', 'row' => '0', 'isLinked' => true, 'unit' => 'px', 'size' => 0],
                'padding' => $this->zeroDimensions(),
                'margin' => $this->zeroDimensions(),
                // Kontainer Elementor membaca kelas dari kunci css_classes;
                // _css_classes hanya dibaca widget.
                'css_classes' => $this->attribute($element, 'class'),
                '_element_id' => $this->attribute($element, 'id'),
            ]),
            'elements' => $this->mapChildren($element, $depth + 1),
        ];
    }

    /**
     * Elementor menumpuk isi kontainer ke bawah secara bawaan. Kelas yang di
     * CSS kandidat sudah display:flex/grid tidak boleh ikut ditumpuk: baris
     * aslinya harus tetap sebaris.
     */
    private function flexDirection(DOMElement $element): string
    {
        foreach (preg_split('/\s+/', $this->attribute($element, 'class')) ?: [] as $class) {
            if ($class !== '' && isset($this->flexContainers[$class])) {
                return 'row';
            }
        }

        return 'column';
    }

    /**
     * @return array<string, mixed>
     */
    private function heading(DOMElement $element): array
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) !== 'br') {
                return $this->textEditor((string) $this->doc->saveHTML($element));
            }
        }

        foreach (preg_split('/\s+/', $this->attribute($element, 'class')) ?: [] as $class) {
            if ($class !== '') {
                $this->headingClasses[] = $class;
            }
        }

        return [
            'id' => $this->nextId(),
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => $this->cleanSettings([
                'title' => trim($this->innerHtml($element)),
                'header_size' => strtolower($element->nodeName),
                '_element_width' => 'auto',
                '_css_classes' => $this->attribute($element, 'class'),
                '_element_id' => $this->attribute($element, 'id'),
            ]),
            'elements' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textEditor(string $markup, string $cssClasses = ''): array
    {
        return [
            'id' => $this->nextId(),
            'elType' => 'widget',
            'widgetType' => 'text-editor',
            'settings' => $this->cleanSettings([
                'editor' => $markup,
                '_element_width' => 'auto',
                '_css_classes' => $cssClasses,
            ]),
            'elements' => [],
        ];
    }

    /**
     * Tautan tanpa teks di sekitarnya tetap jadi widget sendiri. Elementor
     * membungkus isinya dengan <p>; kelas ini mematikan margin bawaan <p>
     * supaya jarak antar tautan mengikuti kontainer flex aslinya.
     *
     * @return array<string, mixed>
     */
    private function inlineLink(DOMElement $element): array
    {
        return $this->textEditor(
            trim((string) $this->doc->saveHTML($element)),
            'siteflow-inline-link'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function image(DOMElement $element, ?DOMElement $link = null): array
    {
        $src = trim($this->attribute($element, 'src'));

        if ($src === '') {
            throw new RuntimeException('Ada <img> tanpa sumber gambar; build dihentikan.');
        }

        if (str_starts_with(strtolower($src), 'data:')) {
            throw new RuntimeException('Gambar tertanam (data:) tidak bisa masuk pustaka media WordPress.');
        }

        $class = $this->attribute($element, 'class');
        $alt = $this->attribute($element, 'alt');

        foreach (preg_split('/\s+/', $class) ?: [] as $className) {
            if ($className !== '') {
                $this->imageClasses[] = $className;
            }
        }

        $settings = [
            'image' => array_filter([
                'url' => $src,
                'id' => '',
                'size' => '',
                'alt' => $alt,
            ], static fn (string $value): bool => $value !== ''),
            'image_size' => 'full',
            '_element_width' => 'auto',
            '_css_classes' => $class,
            '_element_id' => $this->attribute($element, 'id'),
        ];

        if ($link instanceof DOMElement) {
            $settings['link_to'] = 'custom';
            $settings['link'] = $this->linkSettings($link);
        }

        if (preg_match('/^\[\[siteflow:gambar:([A-Za-z0-9_.-]+)\]\]$/', $src, $match) && $alt !== '') {
            $this->imageAlts[$match[1]] = $alt;
        }

        return [
            'id' => $this->nextId(),
            'elType' => 'widget',
            'widgetType' => 'image',
            'settings' => $this->cleanSettings($settings),
            'elements' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function button(DOMElement $element): array
    {
        $href = trim($this->attribute($element, 'href'));
        $text = trim((string) preg_replace('/\s+/', ' ', $element->textContent));

        if ($href === '' || $href === '#') {
            return $this->textEditor(trim($this->innerHtml($element)));
        }

        foreach (preg_split('/\s+/', $this->attribute($element, 'class')) ?: [] as $class) {
            if ($class !== '') {
                $this->buttons[] = $class;
            }
        }

        return [
            'id' => $this->nextId(),
            'elType' => 'widget',
            'widgetType' => 'button',
            'settings' => $this->cleanSettings([
                'text' => $text,
                'link' => $this->linkSettings($element),
                'align' => 'left',
                '_element_width' => 'auto',
                '_css_classes' => '',
                '_element_id' => $this->attribute($element, 'id'),
            ]),
            'elements' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function linkedImage(DOMElement $anchor): ?array
    {
        $image = null;

        foreach ($anchor->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->textContent) === '') {
                continue;
            }

            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'img' && $image === null) {
                $image = $child;
                continue;
            }

            return null;
        }

        return $image instanceof DOMElement ? $this->image($image, $anchor) : null;
    }

    private function isButton(DOMElement $element): bool
    {
        $class = $this->attribute($element, 'class');

        return $class !== '' && preg_match('/\b(button|btn|cta)\b/i', $class) === 1;
    }

    /**
     * @return array{url: string, is_external: string, nofollow: string, custom_attributes: string}
     */
    private function linkSettings(DOMElement $element): array
    {
        return [
            'url' => trim($this->attribute($element, 'href')),
            'is_external' => $this->attribute($element, 'target') === '_blank' ? 'on' : '',
            'nofollow' => '',
            'custom_attributes' => '',
        ];
    }

    /**
     * @return array{top: string, right: string, bottom: string, left: string, unit: string, isLinked: bool}
     */
    private function zeroDimensions(): array
    {
        return ['top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => true];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function cleanSettings(array $settings): array
    {
        return array_filter($settings, static fn ($value): bool => $value !== '' && $value !== null);
    }

    private function attribute(DOMElement $element, string $name): string
    {
        return trim($element->getAttribute($name));
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= (string) $this->doc->saveHTML($child);
        }

        return $html;
    }

    private function nextId(): string
    {
        return substr(md5($this->slug . ':' . $this->counter++), 0, 7);
    }
}
