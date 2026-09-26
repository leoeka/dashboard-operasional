<?php

namespace App\Support;

/**
 * Colour helpers shared by the site renderer and the Gutenberg builder, so a
 * band's text colour is decided once: the preview and WordPress cannot put
 * white text on a band where the other puts dark text.
 */
final class Palette
{
    public const INK = '#1c1a17';
    public const PAPER = '#ffffff';

    public static function hex(mixed $value, string $default): string
    {
        $value = trim(is_string($value) ? $value : '');

        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) ? $value : $default;
    }

    /** Relative-luminance check: is this a background dark text reads on? */
    public static function isLight(string $hex): bool
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return false;
        }

        $luminance = (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2))) / 255;

        return $luminance > 0.6;
    }

    public static function textOn(string $background): string
    {
        return self::isLight($background) ? self::INK : self::PAPER;
    }
}
