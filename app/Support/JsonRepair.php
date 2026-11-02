<?php

namespace App\Support;

/**
 * Closes the brackets of a JSON body a model ran out of tokens to finish.
 *
 * Every large JSON answer in this app risks stopping just before its last
 * closing brace: Gemini's business analysis did it because the model spends
 * part of its budget thinking, and the website content answer is the same size
 * of output. The failure was always the same, so the repair lives in one place
 * instead of a copy per provider.
 */
class JsonRepair
{
    /**
     * Counts brackets outside string literals and appends the ones that were
     * never closed. Not a perfect fix (a body cut off mid-string stays broken
     * apart from the closing quote added here) but it saves the common case:
     * the text stopped one brace short of the end.
     */
    public static function closeTruncated(string $text): string
    {
        $text = rtrim($text);

        // A leftover trailing comma would make even a complete bracket set invalid.
        $text = (string) preg_replace('/,\s*$/', '', $text);

        $stack = [];
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{' || $char === '[') {
                $stack[] = $char;
            } elseif ($char === '}' || $char === ']') {
                array_pop($stack);
            }
        }

        if ($inString) {
            $text .= '"';
        }

        while ($stack !== []) {
            $open = array_pop($stack);
            $text .= ($open === '{') ? '}' : ']';
        }

        return $text;
    }

    /**
     * Decode a model's JSON answer, tolerating a markdown fence and a body that
     * stopped before its closing bracket.
     *
     * @return array{ok: bool, value: mixed, error: string}
     */
    public static function decodeModelJson(string $text): array
    {
        $cleaned = trim($text);
        $cleaned = (string) preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = (string) preg_replace('/\s*```$/', '', $cleaned);

        $value = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return ['ok' => true, 'value' => $value, 'error' => ''];
        }

        $error = json_last_error_msg();
        $repaired = json_decode(self::closeTruncated($cleaned), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return ['ok' => true, 'value' => $repaired, 'error' => 'truncated'];
        }

        return ['ok' => false, 'value' => null, 'error' => $error];
    }
}
