<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMText;

class ElementorSvgMarkup
{
    private const SVG_TAGS = [
        'svg', 'g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse',
        'defs', 'lineargradient', 'stop', 'clippath', 'mask', 'title', 'desc', 'text', 'tspan',
    ];

    private const SVG_ATTRIBUTES = [
        'id', 'class', 'viewbox', 'width', 'height', 'fill', 'fill-opacity', 'fill-rule',
        'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray',
        'stroke-dashoffset', 'stroke-opacity', 'opacity', 'transform', 'cx', 'cy', 'r',
        'x', 'y', 'x1', 'x2', 'y1', 'y2', 'rx', 'ry', 'd', 'points', 'offset',
        'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'clip-path',
        'clippathunits', 'mask', 'maskunits', 'maskcontentunits', 'vector-effect',
        'preserveaspectratio', 'aria-hidden', 'aria-label', 'role', 'focusable',
        'font-size', 'text-anchor', 'dominant-baseline', 'xmlns',
    ];

    private const SVG_TAG_CASE = [
        'lineargradient' => 'linearGradient',
        'clippath' => 'clipPath',
        'tspan' => 'tspan',
    ];

    private const SVG_ATTRIBUTE_CASE = [
        'viewbox' => 'viewBox',
        'gradientunits' => 'gradientUnits',
        'gradienttransform' => 'gradientTransform',
        'clippathunits' => 'clipPathUnits',
        'maskunits' => 'maskUnits',
        'maskcontentunits' => 'maskContentUnits',
        'preserveaspectratio' => 'preserveAspectRatio',
    ];

    public static function sanitize(string $markup, string $idPrefix = 'siteflow-svg'): ?string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="siteflow-svg-root">' . $markup . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        $root = (new \DOMXPath($document))->query('//*[@id="siteflow-svg-root"]')->item(0);

        if (!$root instanceof DOMElement) {
            return null;
        }

        $parts = [];
        $svgCount = 0;

        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->textContent) === '') {
                continue;
            }

            if ($child instanceof DOMText) {
                return null;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'svg') {
                $svgCount++;
                $safe = self::svgElement($child, $idPrefix);
            } elseif ($tag === 'a') {
                $safe = self::linkElement($child, $idPrefix, $svgCount);
            } else {
                return null;
            }

            if ($safe === null) {
                return null;
            }

            $parts[] = $safe;
        }

        return $svgCount > 0 && $parts !== [] ? implode('', $parts) : null;
    }

    private static function linkElement(DOMElement $link, string $idPrefix, int &$svgCount): ?string
    {
        $href = trim($link->getAttribute('href'));

        if (!self::safeLink($href)) {
            return null;
        }

        $attributes = ['href' => $href];

        foreach (['class', 'aria-label', 'title'] as $name) {
            if ($link->hasAttribute($name)) {
                $value = trim($link->getAttribute($name));

                if ($value !== '' && preg_match('/^[A-Za-z0-9_ .:-]+$/', $value) !== 1) {
                    return null;
                }

                if ($value !== '') {
                    $attributes[$name] = $value;
                }
            }
        }

        $inner = '';
        $hasSvg = false;

        foreach ($link->childNodes as $child) {
            if ($child instanceof DOMText) {
                $inner .= self::escape($child->textContent);
                continue;
            }

            if (!$child instanceof DOMElement || strtolower($child->tagName) !== 'svg') {
                return null;
            }

            $safeSvg = self::svgElement($child, $idPrefix . '-' . $svgCount);

            if ($safeSvg === null) {
                return null;
            }

            $svgCount++;
            $hasSvg = true;
            $inner .= $safeSvg;
        }

        if (!$hasSvg) {
            return null;
        }

        return '<a' . self::attributes($attributes) . '>' . $inner . '</a>';
    }

    private static function svgElement(DOMElement $svg, string $idPrefix): ?string
    {
        if (strtolower($svg->tagName) !== 'svg') {
            return null;
        }

        $ids = [];
        $collectIds = function (DOMElement $element) use (&$collectIds, &$ids): bool {
            $id = trim($element->getAttribute('id'));

            if ($id !== '') {
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $id) !== 1 || isset($ids[$id])) {
                    return false;
                }

                $ids[$id] = '';
            }

            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && !$collectIds($child)) {
                    return false;
                }
            }

            return true;
        };

        if (!$collectIds($svg)) {
            return null;
        }

        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '-', $idPrefix) ?: 'siteflow-svg';

        foreach (array_keys($ids) as $id) {
            $ids[$id] = $prefix . '-' . $id;
        }

        return self::renderSvgNode($svg, $ids, true);
    }

    private static function renderSvgNode(DOMElement $element, array $ids, bool $root = false): ?string
    {
        $lowerTag = strtolower($element->tagName);

        if (!in_array($lowerTag, self::SVG_TAGS, true)) {
            return null;
        }

        $attributes = [];

        if ($root) {
            $attributes['xmlns'] = 'http://www.w3.org/2000/svg';
        }

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (!in_array($name, self::SVG_ATTRIBUTES, true) || preg_match('/[<>\x00-\x1F]/', $value)) {
                continue;
            }

            if ($name === 'xmlns') {
                if ($value !== 'http://www.w3.org/2000/svg') {
                    return null;
                }

                continue;
            }

            if ($name === 'id') {
                if (!isset($ids[$value])) {
                    return null;
                }

                $value = $ids[$value];
            } elseif (str_contains($value, 'url(')) {
                if (!preg_match_all('/url\(#([A-Za-z_][A-Za-z0-9_.:-]*)\)/', $value, $references)
                    || preg_replace('/url\(#([A-Za-z_][A-Za-z0-9_.:-]*)\)/', '', $value) !== '') {
                    return null;
                }

                foreach ($references[1] as $reference) {
                    if (!isset($ids[$reference])) {
                        return null;
                    }
                }

                $value = preg_replace_callback(
                    '/url\(#([A-Za-z_][A-Za-z0-9_.:-]*)\)/',
                    static fn (array $match): string => 'url(#' . $ids[$match[1]] . ')',
                    $value
                );
            }

            if ($name === 'class' && preg_match('/^[A-Za-z0-9_ .:-]*$/', $value) !== 1) {
                continue;
            }

            $attributes[self::SVG_ATTRIBUTE_CASE[$name] ?? $name] = $value;
        }

        $tag = self::SVG_TAG_CASE[$lowerTag] ?? $lowerTag;
        $inner = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) {
                if (trim($child->textContent) !== '' && !in_array($lowerTag, ['text', 'tspan', 'title', 'desc'], true)) {
                    return null;
                }

                $inner .= self::escape($child->textContent);
                continue;
            }

            if ($child instanceof DOMElement) {
                $safeChild = self::renderSvgNode($child, $ids);

                if ($safeChild === null) {
                    return null;
                }

                $inner .= $safeChild;
            }
        }

        return '<' . $tag . self::attributes($attributes) . '>' . $inner . '</' . $tag . '>';
    }

    private static function safeLink(string $href): bool
    {
        if ($href === '' || preg_match('/[\x00-\x20]/', $href)) {
            return false;
        }

        if (preg_match('/^(?:https:\/\/|mailto:|tel:|#|\/|\.\/|\.\.\/|\[\[siteflow:halaman:[a-z0-9-]+\]\])/i', $href) === 1) {
            return !str_starts_with($href, '//');
        }

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._~-]*(?:[/?#][^\s]*)?$/', $href) === 1;
    }

    private static function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . self::escape((string) $value) . '"';
        }

        return $html;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
