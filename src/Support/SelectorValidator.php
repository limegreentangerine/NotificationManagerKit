<?php

namespace NotificationManagerKit\Support;

/**
 * Admin-supplied CSS selectors (click / scroll-to-element triggers) end up in querySelector() and closest().
 * Only a conservative subset is accepted: ids, classes, tags, attribute-presence/equality, descendant and child combinators, commas.
 */
final class SelectorValidator
{
    private const SIMPLE = '(?:[a-zA-Z][a-zA-Z0-9-]*|\*)?(?:[#.][a-zA-Z_][\w-]*|\[[a-zA-Z][\w-]*(?:=(?:"[^"\\\\<>]*"|\'[^\'\\\\<>]*\'|[\w-]+))?\])*';

    public static function isValid(string $selector): bool
    {
        $selector = trim($selector);

        if ($selector === '' || strlen($selector) > 200) {
            return false;
        }

        $compound = '(?=[^\s>,])' . self::SIMPLE;
        $complex = $compound . '(?:\s*>\s*|\s+)?(?:' . $compound . '(?:\s*>\s*|\s+)?)*';

        return (bool) preg_match('/^' . $complex . '(?:\s*,\s*' . $complex . ')*$/', $selector)
            && !preg_match('/[>\s,]$/', $selector);
    }

    /**
     * The selector if it is acceptable, otherwise null.
     */
    public static function sanitize(?string $selector): ?string
    {
        $selector = trim((string) $selector);

        return self::isValid($selector) ? $selector : null;
    }
}
