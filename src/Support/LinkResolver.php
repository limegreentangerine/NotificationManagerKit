<?php

namespace NotificationManagerKit\Support;

/**
 * Turns what an admin typed into a safe href for the link types that are free text (external URL, phone, email).
 * Page and file links are resolved from the database elsewhere. A value that doesn't validate resolves to null,
 * which means "no link": nothing unvalidated is ever written into an href.
 */
final class LinkResolver
{
    public const TYPE_NONE = 'none';
    public const TYPE_PAGE = 'page';
    public const TYPE_FILE = 'file';
    public const TYPE_URL = 'url';
    public const TYPE_PHONE = 'phone';
    public const TYPE_EMAIL = 'email';

    public const TYPES = [
        self::TYPE_NONE => 'No link',
        self::TYPE_PAGE => 'Page',
        self::TYPE_FILE => 'File',
        self::TYPE_URL => 'External link',
        self::TYPE_PHONE => 'Phone number',
        self::TYPE_EMAIL => 'Email address',
    ];

    /**
     * Types whose value is typed in (as opposed to picked from the sitemap or file manager).
     */
    public const TEXT_TYPES = [self::TYPE_URL, self::TYPE_PHONE, self::TYPE_EMAIL];

    public static function isValidType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * An absolute http(s) URL. A bare "example.com/path" gets https:// added; other schemes (javascript:, data:, ftp:...) are refused.
     */
    public static function url(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 2000 || preg_match('/[\x00-\x20<>"\'`\\\\]/', $value)) {
            return null;
        }

        // A host needs a dot and, if followed by a colon, a numeric port, so "javascript:..." can never look like one.
        if (preg_match('#^[a-z0-9-]+(\.[a-z0-9-]+)+(?::\d+)?(?:[/?\#]|$)#i', $value)) {
            $value = 'https://' . $value;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($value);

        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        return $value;
    }

    /**
     * A tel: URI. Spaces, dots, dashes and brackets are allowed as typed, then dropped; only an optional leading + and 5-15 digits remain.
     */
    public static function phone(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || !preg_match('/^\+?[\d\s().\-]+$/', $value)) {
            return null;
        }

        $number = preg_replace('/[\s().\-]/', '', $value);

        return preg_match('/^\+?\d{5,15}$/', $number) ? 'tel:' . $number : null;
    }

    /**
     * A mailto: URI for a plain address (no display names, quoted local parts or other RFC oddities).
     */
    public static function email(?string $value): ?string
    {
        $value = trim((string) $value);

        if (strlen($value) > 254 || !preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/', $value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $value : null;
    }

    /**
     * The href for a free text link type, or null when the type isn't one of those or the value is invalid.
     */
    public static function href(string $type, ?string $value): ?string
    {
        return match ($type) {
            self::TYPE_URL => self::url($value),
            self::TYPE_PHONE => self::phone($value),
            self::TYPE_EMAIL => self::email($value),
            default => null,
        };
    }

    /**
     * What to show as the link text when the admin left it blank: the number, the address, or the site's host.
     */
    public static function defaultText(string $type, ?string $value): string
    {
        $value = trim((string) $value);

        return match ($type) {
            self::TYPE_PHONE, self::TYPE_EMAIL => $value,
            self::TYPE_URL => (string) (parse_url((string) self::url($value), PHP_URL_HOST) ?: ''),
            default => '',
        };
    }
}
