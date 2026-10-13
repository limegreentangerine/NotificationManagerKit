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
     * @return array{banners: NotificationView[], popup: NotificationView|null}
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

        // Newest edit breaks a priority tie, so the result is stable regardless of database row order.
        $byPriority = fn(NotificationView $a, NotificationView $b) => [$b->priority, $b->version, $a->id] <=> [$a->priority, $a->version, $b->id];
        usort($banners, $byPriority);
        usort($popups, $byPriority);

        return ['banners' => $banners, 'popup' => $popups[0] ?? null];
    }
}
