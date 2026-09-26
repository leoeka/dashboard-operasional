<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Reads a blueprint section's content the same way for every renderer.
 *
 * The content stage writes items in whatever shape suits them — {title,
 * description} for a feature, {question, answer} for an FAQ, {quote, author}
 * for a testimonial, {value, label} for a statistic. Each renderer used to dig
 * those fields out itself; the live preview and the Gutenberg builder must pick
 * the SAME field for the same slot, or the demo and the delivered page would
 * say different things. So both read through here.
 */
final class SectionContent
{
    /**
     * @return array<int, array{title:string, text:string, quote:string, author:string, role:string, value:string, label:string, price:string, features:array<int,string>}>
     *         keyed by the item's ORIGINAL index, because photographs are
     *         assigned to items by that index.
     */
    public static function items(array $section, int $limit = 0): array
    {
        $items = is_array($section['items'] ?? null) ? array_values($section['items']) : [];
        $normalized = [];

        foreach ($items as $index => $item) {
            $entry = is_array($item) ? self::normalize($item) : self::normalize(['title' => $item]);

            if ($entry['title'] === '' && $entry['text'] === '' && $entry['quote'] === '' && $entry['value'] === '') {
                continue;
            }

            $normalized[$index] = $entry;

            if ($limit > 0 && count($normalized) >= $limit) {
                break;
            }
        }

        return $normalized;
    }

    public static function text(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            return trim(implode(', ', array_filter(array_map(
                fn ($part) => is_string($part) || is_numeric($part) ? (string) $part : null,
                $value
            ))));
        }

        return '';
    }

    /**
     * Testimonials as quote + attribution. An item written as {title,
     * description} has its words in the description and the person in the
     * title; one with only a title is a quote with no attribution.
     *
     * @return array<int, array{quote:string, author:string, role:string}>
     */
    public static function quotes(array $items): array
    {
        return array_values(array_map(function (array $item) {
            $quote = $item['quote'] !== '' ? $item['quote'] : $item['title'];

            return [
                'quote' => $quote,
                'author' => $item['author'] !== $quote ? $item['author'] : '',
                'role' => $item['role'],
            ];
        }, $items));
    }

    /**
     * The pricing plan drawn as highlighted: only one the blueprint explicitly
     * flags as featured — which ContentIntegrityService keeps only when the
     * client singled that plan out. Never a tier picked by position; the
     * always-highlighted middle card is a template habit, not a fact.
     *
     * @param array<int, array{featured?:bool}> $items normalised items, in display order
     */
    public static function featuredIndex(array $items): ?int
    {
        foreach (array_values($items) as $position => $item) {
            if (!empty($item['featured'])) {
                return $position;
            }
        }

        return null;
    }

    /** Two-letter monogram for a person or brand shown without a photograph. */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';

        foreach (array_slice(array_filter($words), 0, 2) as $word) {
            $letters .= Str::upper(Str::substr($word, 0, 1));
        }

        return $letters !== '' ? $letters : '•';
    }

    private static function normalize(array $item): array
    {
        $item = array_change_key_case($item, CASE_LOWER);
        $first = function (array $keys) use ($item): string {
            foreach ($keys as $key) {
                $value = self::text($item[$key] ?? null);
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        };

        $title = $first(['title', 'name', 'question', 'pertanyaan', 'plan', 'label']);
        $text = $first(['description', 'answer', 'jawaban', 'text', 'content', 'body']);
        $value = $first(['value', 'number', 'stat', 'angka', 'count']);

        $features = [];
        foreach (['features', 'benefits', 'includes', 'fitur'] as $key) {
            if (is_array($item[$key] ?? null)) {
                $features = array_values(array_filter(array_map([self::class, 'text'], $item[$key])));
                break;
            }
        }

        return [
            'title' => $title,
            'text' => $text,
            'quote' => $first(['quote', 'testimonial', 'ulasan', 'review']) ?: $text,
            'author' => $first(['author', 'reviewer', 'name', 'title']),
            'role' => $first(['role', 'position', 'jabatan', 'company', 'location']),
            // A statistic may arrive as {value, label} or as {title: "120+", description: "klien"}.
            'value' => $value !== '' ? $value : $title,
            'label' => $value !== '' ? ($first(['label']) ?: $title ?: $text) : $text,
            'price' => $first(['price', 'harga', 'amount', 'monthly']),
            'features' => $features,
            'featured' => (bool) array_filter(
                array_intersect_key($item, array_flip(['featured', 'is_featured', 'highlight', 'highlighted', 'recommended', 'popular'])),
                fn ($flag) => $flag === true || $flag === 1 || $flag === '1' || $flag === 'true'
            ),
        ];
    }
}
