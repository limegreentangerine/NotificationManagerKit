<?php

namespace NotificationManagerKit\Support;

use NotificationManagerKit\Entity\AbstractNotification;

/**
 * Decides, with no database access, which cached notifications apply to a page right now:
 * every eligible banner (ordered by priority), and at most one popup (the highest priority).
 */
final class Eligibility
{
    /**
     * `popup` is the highest priority popup. `popups` is the ordered list of popups the page should carry: audience
     * rules are checked in the browser, so when the top popup may not apply to a visitor the next one has to be on
     * the page too. The list ends at the first popup with no rules, since nothing below it could ever be reached.
     *
     * @return array{banners: NotificationView[], popup: NotificationView|null, popups: NotificationView[]}
     */
    public static function select(CatalogData $catalog, int $cID, ?\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $banners = [];
        $popups = [];

        foreach ($catalog->notifications as $view) {
            if (!$view->isVisibleOnPage($cID) || !$view->schedule->isActiveAt($now)) {
                continue;
            }

            if ($view->type === AbstractNotification::TYPE_BANNER && $catalog->bannersEnabled) {
                $banners[] = $view;
            } elseif ($view->type === AbstractNotification::TYPE_POPUP && $catalog->popupsEnabled) {
                $popups[] = $view;
            }
        }

        // Newest edit breaks a priority tie, then the most recently created, then id, so the result is stable regardless of database row order.
        $byPriority = fn(NotificationView $a, NotificationView $b) => [$b->priority, $b->version, $b->created, $a->id] <=> [$a->priority, $a->version, $a->created, $b->id];
        usort($banners, $byPriority);
        usort($popups, $byPriority);

        $candidates = [];
        foreach ($popups as $popup) {
            $candidates[] = $popup;

            if ($popup->audience === []) {
                break;
            }
        }

        return ['banners' => $banners, 'popup' => $popups[0] ?? null, 'popups' => $candidates];
    }
}
