<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The one rule for which page is Home and what every page's slug is.
 *
 * The live preview's navigation, the asset manifest (keyed by page slug), the
 * implementation manifest and the WordPress importer all address pages by slug.
 * Each used to compute it inline, and not identically: the PNG treated the page
 * NAMED "Home" as home while the builder treated INDEX 0 as home, and two pages
 * whose names slugged alike silently overwrote each other in the build.
 */
final class SitemapPages
{
    /**
     * Pages in build order — Home first — each with a unique slug.
     *
     * @return array<int, array{slug:string, name:string, index:int, page:array}>
     *         `index` is the page's position in the ORIGINAL blueprint list.
     */
    public static function ordered(array $pages): array
    {
        $pages = array_values($pages);
        $homeIndex = self::homeIndex($pages);
        $order = [];

        if ($homeIndex !== null) {
            $order[] = $homeIndex;
        }
        foreach ($pages as $index => $page) {
            if ($index !== $homeIndex) {
                $order[] = $index;
            }
        }

        $used = [];
        $result = [];

        foreach ($order as $index) {
            $page = $pages[$index];
            if (!is_array($page)) {
                continue;
            }

            $name = SectionContent::text($page['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $base = $index === $homeIndex ? 'home' : (Str::slug($name) ?: 'page-' . ($index + 1));
            $slug = $base;
            $suffix = 2;

            // "home" is reserved for the front page, so a second page that
            // happens to be called Home cannot take over the front page slot.
            while (isset($used[$slug]) || ($index !== $homeIndex && $slug === 'home')) {
                $slug = $base . '-' . $suffix++;
            }

            $used[$slug] = true;
            $result[] = ['slug' => $slug, 'name' => $name, 'index' => $index, 'page' => $page];
        }

        return $result;
    }

    /** The page called "Home", else the first page — the rule every renderer shares. */
    public static function homeIndex(array $pages): ?int
    {
        $pages = array_values($pages);

        foreach ($pages as $index => $page) {
            if (is_array($page) && strtolower(trim(SectionContent::text($page['name'] ?? ''))) === 'home') {
                return $index;
            }
        }

        return is_array($pages[0] ?? null) ? 0 : null;
    }
}
