<?php

namespace NotificationManagerKit\Support;

/**
 * Reads and vets what the browser reports about a notification (shown, clicked, dismissed). Pure: no database, no
 * Concrete. Everything arrives from the public internet, so nothing is trusted: ids must look like ids, event codes
 * come from a short list, and a request can only ever add one of each event per notification.
 */
final class TrackRequest
{
    private const BOTS = '/bot|crawl|spider|slurp|headless|lighthouse|pingdom|uptime|curl|wget|python-requests|httpclient|facebookexternalhit|bingpreview|phantomjs/i';
    public const EVENTS = ['i' => 'impressions', 'c' => 'clicks', 'd' => 'dismissals'];

    /** Events read from one request; a page view never produces more than a handful. */
    public const MAX_EVENTS = 20;

    public const MAX_BYTES = 4096;

    /**
     * @return array{language: string, counts: array<string, array{impressions: int, clicks: int, dismissals: int}>}|null
     *                                                                                                                    Null when there is nothing valid to record
     */
    public static function parse(string $body): ?array
    {
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $data = json_decode($body, true);

        if (!is_array($data) || !is_string($data['lang'] ?? null) || !is_array($data['events'] ?? null)) {
            return null;
        }

        if (!preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?$/', $data['lang'])) {
            return null;
        }

        $counts = [];
        $read = 0;

        foreach ($data['events'] as $event) {
            if ($read >= self::MAX_EVENTS) {
                break;
            }

            if (!is_array($event) || count($event) !== 2 || !isset($event[0], $event[1]) || !is_string($event[0]) || !is_string($event[1])) {
                continue;
            }

            [$id, $code] = array_values($event);

            if (!isset(self::EVENTS[$code]) || !self::isValidId($id)) {
                continue;
            }

            $read++;
            $id = strtolower($id);
            $counts[$id] ??= ['impressions' => 0, 'clicks' => 0, 'dismissals' => 0];
            // One of each per notification per request: a page view shows, and is clicked or dismissed, once.
            $counts[$id][self::EVENTS[$code]] = 1;
        }

        return $counts === [] ? null : ['language' => $data['lang'], 'counts' => $counts];
    }

    public static function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    /**
     * Crawlers and tools don't count as visitors. A missing user agent is treated the same way.
     */
    public static function isBot(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent === '' || (bool) preg_match(self::BOTS, $userAgent);
    }

    /**
     * Browsers send an Origin header on a cross-site POST, so a mismatch is a page on another site calling this.
     * No Origin means a non-browser client, which is no more dangerous than one that forges the header.
     */
    public static function isSameOrigin(?string $origin, string $host): bool
    {
        if ($origin === null || $origin === '') {
            return true;
        }

        $parts = parse_url($origin);

        if (!is_array($parts) || !isset($parts['host'])) {
            return false;
        }

        $originHost = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $host = strtolower($host);

        return $originHost === $host || (!str_contains($host, ':') && strtolower($parts['host']) === $host);
    }
}
