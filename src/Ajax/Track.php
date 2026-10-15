<?php

namespace NotificationManagerKit\Ajax;

use Concrete\Core\Page\Page;
use Concrete\Core\User\User;
use Concrete\Core\Http\Request;
use Concrete\Core\Permission\Checker;
use Symfony\Component\HttpFoundation\Response;
use NotificationManagerKit\Support\{Catalog, StatsStore, TrackRequest};

/**
 * Receives the beacon a visitor's browser sends about what it showed, and adds it to today's counters.
 * It is public and cannot carry a CSRF token (pages are cached), so it is defensive instead: it only ever adds
 * to counters of notifications that are live right now, ignores bots and signed-in administrators, caps how much
 * one address can report, and always answers 204 with nothing in the body, so it says nothing to a prober.
 */
class Track
{
    /** Events one address may report per minute (several visitors can share an office or a mobile carrier). */
    private const EVENTS_PER_MINUTE = 300;

    private function handle(Request $request): void
    {
        if (!$request->isMethod('POST')
            || !TrackRequest::isSameOrigin($request->headers->get('Origin'), $request->getHttpHost())
            || TrackRequest::isBot($request->headers->get('User-Agent'))) {
            return;
        }

        $parsed = TrackRequest::parse((string) $request->getContent());

        if ($parsed === null || !$this->isSiteLanguage($parsed['language'])) {
            return;
        }

        $catalog = Catalog::load($parsed['language']);

        if (!$catalog->statsEnabled) {
            return;
        }

        // Only notifications that are live: unknown ids are dropped, so nothing can be written for a made-up id.
        $live = array_flip(array_map(fn($view) => $view->id, $catalog->notifications));
        $counts = array_intersect_key($parsed['counts'], $live);

        if ($counts === [] || $this->isAdministrator() || !$this->withinLimit($request, count($counts))) {
            return;
        }

        StatsStore::make()->record($counts, $parsed['language']);
    }

    /**
     * The language comes from the visitor, and Catalog keeps one cache entry per language, so only the site's own
     * languages are allowed to create one.
     */
    private function isSiteLanguage(string $language): bool
    {
        foreach (app('site')->getSite()->getLocales() as $locale) {
            if ($locale->getLanguage() === $language) {
                return true;
            }
        }

        return false;
    }

    /**
     * Editors previewing their own pages would otherwise inflate the figures.
     */
    private function isAdministrator(): bool
    {
        $user = new User();

        if (!$user->isRegistered()) {
            return false;
        }

        return (new Checker(Page::getByPath('/dashboard/notification_manager')))->getResponseObject()->validate('view_page');
    }

    private function withinLimit(Request $request, int $events): bool
    {
        $cache = app('cache/expensive');
        $item = $cache->getItem('notification_manager_kit/track/' . sha1((string) $request->getClientIp()));
        $now = time();
        $state = $item->get();

        // The window start is kept in the value, not in the cache expiry: an expiry that moves on every save would
        // let a steady trickle of events from one address accumulate until it was blocked for good.
        if ($item->isMiss() || !is_array($state) || $now - (int) ($state['start'] ?? 0) >= 60) {
            $state = ['start' => $now, 'used' => 0];
        }

        if ($state['used'] + $events > self::EVENTS_PER_MINUTE) {
            return false;
        }

        $state['used'] += $events;
        $item->set($state);
        $item->expiresAfter(120);
        $cache->save($item);

        return true;
    }

    public function collect(): Response
    {
        try {
            $this->handle(app(Request::class));
        } catch (\Throwable) {
            // Statistics must never break, slow or error a page for the visitor.
        }

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
