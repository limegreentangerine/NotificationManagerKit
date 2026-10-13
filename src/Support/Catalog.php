<?php

namespace NotificationManagerKit\Support;

use Concrete\Core\Page\Page;
use Concrete\Core\Cache\Page\PageCache;
use Doctrine\ORM\EntityManagerInterface;
use Concrete\Core\Entity\File\File as FileEntity;
use NotificationManagerKit\Entity\{AbstractNotification, BannerNotification, PopupNotification, Settings};

/**
 * Reads the settings and active notifications once, turns them into plain data, and keeps that in Concrete's
 * "expensive" cache. Every save calls flush(), so the cache never outlives an edit; schedules are checked per
 * request against the cached data (see Eligibility), so it needs no short lifetime to stay correct.
 */
final class Catalog
{
    private const KEY = 'notification_manager_kit/catalog';
    private const LIFETIME = 43200;

    private static ?CatalogData $memo = null;
    private static ?string $memoLanguage = null;

    private static function keyPart(string $language): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '_', $language);
    }

    private static function build(string $language): CatalogData
    {
        $settings = Settings::get();
        $fallback = $settings->getDefaultLanguage();
        $em = app(EntityManagerInterface::class);

        $entities = $em->createQuery(
            'SELECT n, d, p FROM ' . AbstractNotification::class . ' n LEFT JOIN n.data d LEFT JOIN n.pages p WHERE n.active = true',
        )->getResult();

        $views = [];
        foreach ($entities as $notification) {
            $content = $notification->getContent($language) ?: $notification->getContent($fallback);

            if ($content && ($view = self::view($notification, $content))) {
                $views[] = $view;
            }
        }

        return new CatalogData(
            $settings->areBannersEnabled(),
            $settings->arePopupsEnabled(),
            $settings->usesCustomCss(),
            $settings->getExcludedPaths(),
            $views,
        );
    }

    private static function view(AbstractNotification $notification, \NotificationManagerKit\Entity\NotificationContent $content): ?NotificationView
    {
        $page = $content->getPage();
        $linkText = $content->getLinkText();

        if ($page) {
            $linkUrl = (string) $page->getCollectionLink();
            $linkText = $linkText !== '' ? $linkText : (string) $page->getCollectionName();
        }

        $options = [];
        $image = null;

        if ($notification instanceof BannerNotification) {
            $options = [
                'transition' => $notification->getTransition(),
                'interval' => $notification->getInterval(),
                'dismissible' => $notification->isDismissible(),
            ];
        } elseif ($notification instanceof PopupNotification) {
            if (!$notification->hasUsableTrigger()) {
                return null;
            }

            $options = [
                'frequency' => $notification->getFrequency(),
                'onLoad' => $notification->isTriggerOnLoad(),
                'delay' => $notification->getTriggerDelayMs(),
                'onScroll' => $notification->isTriggerOnScroll(),
                'scrollValue' => $notification->getTriggerScrollValue(),
                'scrollUnit' => $notification->getTriggerScrollUnit(),
                'scrollElement' => $notification->isTriggerOnScrollToElement() ? $notification->getTriggerScrollElementSelector() : '',
                'click' => $notification->isTriggerOnClick() ? $notification->getTriggerClickSelector() : '',
            ];

            $file = $content->getImage();
            if ($file instanceof FileEntity) {
                $version = $file->getApprovedVersion();
                $image = $version ? [
                    'url' => (string) $version->getURL(),
                    'width' => (int) $version->getAttribute('width'),
                    'height' => (int) $version->getAttribute('height'),
                    'alt' => Sanitizer::text((string) $version->getTitle()),
                ] : null;
            }
        }

        return new NotificationView(
            $notification->getID(),
            $notification->getType(),
            $notification->getPriority(),
            $notification->getDateUpdated()->getTimestamp(),
            $notification->getSchedule(),
            $notification->getDisplayScope(),
            $notification->getPageIds(),
            $content->getName(),
            $content->getContent(),
            $linkUrl ?? '',
            $linkText,
            $image,
            $options,
        );
    }

    public static function load(string $language): CatalogData
    {
        if (self::$memo !== null && self::$memoLanguage === $language) {
            return self::$memo;
        }

        try {
            $cache = app('cache/expensive');
            $item = $cache->getItem(self::KEY . '/' . self::keyPart($language));
            $data = $item->get();

            if ($item->isMiss() || !$data instanceof CatalogData) {
                $item->lock();
                $data = self::build($language);
                $item->set($data);
                $item->expiresAfter(self::LIFETIME);
                $cache->save($item);
            }
        } catch (\Throwable) {
            // eg. before the upgrade that creates the tables has run: show nothing rather than take every page down.
            $data = new CatalogData();
        }

        self::$memo = $data;
        self::$memoLanguage = $language;

        return $data;
    }

    /**
     * Drop every cached catalog and the full page cache (which has notification markup baked into it).
     */
    public static function flush(): void
    {
        self::$memo = null;
        self::$memoLanguage = null;

        try {
            app('cache/expensive')->delete(self::KEY);
            PageCache::getLibrary()->flush();
        } catch (\Throwable) {
            // Nothing to invalidate if the cache isn't available.
        }
    }
}
