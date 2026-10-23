<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

final class SafeHtml
{
    private const ALLOWED_TAGS = [
        'a', 'br', 'blockquote', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4',
        'hr', 'li', 'ol', 'p', 'pre', 'small', 'span', 'strong', 'table',
        'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $html = strip_tags($html, '<'.implode('><', self::ALLOWED_TAGS).'>');

        return (string) preg_replace_callback(
            '/<([a-z0-9]+)([^>]*)>/i',
            static function (array $match): string {
                $tag = strtolower($match[1]);
                $attrs = self::cleanAttributes($tag, $match[2] ?? '');

                return '<'.$tag.$attrs.'>';
            },
            $html
        );
    }

    public static function render(?string $html): HtmlString
    {
        return new HtmlString(self::clean($html));
    }

    public static function text(?string $html): string
    {
        return trim(html_entity_decode(strip_tags(self::clean($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function cleanAttributes(string $tag, string $rawAttributes): string
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        if ($allowed === []) {
            return '';
        }

        $attributes = [];
        preg_match_all('/([a-z0-9:-]+)\s*=\s*(["\'])(.*?)\2/i', $rawAttributes, $matches, PREG_SET_ORDER);

        foreach ($matches as $attribute) {
            $name = strtolower($attribute[1]);
            $value = trim(html_entity_decode($attribute[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if (! in_array($name, $allowed, true)) {
                continue;
            }

            if ($name === 'href' && ! self::isSafeUrl($value)) {
                continue;
            }

            if (in_array($name, ['colspan', 'rowspan'], true) && ! preg_match('/^[1-9][0-9]?$/', $value)) {
                continue;
            }

            $attributes[] = $name.'="'.e($value).'"';
        }

        return $attributes === [] ? '' : ' '.implode(' ', $attributes);
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === '' || in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
