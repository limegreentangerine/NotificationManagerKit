<?php

namespace NotificationManagerKit\Support;

final class Sanitizer
{
    /**
     * Strip scripts, event handlers, embeds and unsafe URL schemes from rich text.
     * Run on save and again before output, so neither a bad write path nor a bad row in the database can reach a page.
     */
    public static function html(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return htmLawed($html, [
            'safe' => 1,
            'elements' => '*-script-iframe-object-embed-applet-form-style-link-meta-base',
            'deny_attribute' => 'on*, style',
            'schemes' => 'href: http, https, mailto, tel; src: http, https',
            'balance' => 1,
        ]);
    }

    /**
     * Single-line plain text (names, link text): no markup at all.
     */
    public static function text(?string $text, int $maxLength = 255): string
    {
        $text = trim(strip_tags((string) $text));

        return mb_substr($text, 0, $maxLength);
    }
}
