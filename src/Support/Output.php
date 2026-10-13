<?php

namespace NotificationManagerKit\Support;

/**
 * Escaping for everything the package writes into HTML. Entity getters are never echoed raw.
 */
final class Output
{
    /**
     * For text content and quoted attribute values.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A URL for an href/src: http(s), mailto, tel, or a relative URL. Anything else (javascript:, data:, ...) becomes '#'.
     */
    public static function url(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '#';
        }

        // Control characters and whitespace inside the scheme are how "java\tscript:" bypasses get through.
        $normalised = preg_replace('/[\x00-\x20]+/', '', $url);

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $normalised, $match)) {
            if (!in_array(strtolower($match[1]), ['http', 'https', 'mailto', 'tel'], true)) {
                return '#';
            }
        }

        return self::e($url);
    }

    /**
     * JSON that is safe inside an HTML attribute or a <script> block.
     */
    public static function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
}
