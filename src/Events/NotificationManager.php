<?php

namespace NotificationManagerKit\Events;

use Concrete\Core\Page\Page;
use Concrete\Core\View\View;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Localization\Localization;
use NotificationManagerKit\Support\{Catalog, CatalogData, Eligibility};

/**
 * Front end entry point. Cheap by construction: with nothing to show it costs one cache read and no queries,
 * and assets are only requested on pages that actually display something.
 */
class NotificationManager
{
    private const PACKAGE = 'notification_manager_kit';

    private static string $bannerHtml = '';
    private static bool $outputHooked = false;

    private static function hookOutput(): void
    {
        if (!self::$outputHooked) {
            self::$outputHooked = true;
            app('director')->addListener('on_page_output', [self::class, 'onPageOutput']);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function render(string $element, array $data): string
    {
        ob_start();
        View::element($element, $data, self::PACKAGE);

        return (string) ob_get_clean();
    }

    private static function registerAssets(): void
    {
        $list = AssetList::getInstance();
        $options = ['minify' => true, 'combine' => true];

        $list->register('css', 'notification-manager-kit/banner', 'css/nm-banner.css', $options, self::PACKAGE);
        $list->register('css', 'notification-manager-kit/popup', 'css/nm-popup.css', $options, self::PACKAGE);
        $list->register('javascript', 'notification-manager-kit/storage', 'js/nm-storage.js', $options, self::PACKAGE);
        $list->register('javascript', 'notification-manager-kit/audience', 'js/nm-audience.js', $options, self::PACKAGE);
        $list->register('javascript', 'notification-manager-kit/banner', 'js/nm-banner.js', $options, self::PACKAGE);
        $list->register('javascript', 'notification-manager-kit/popup', 'js/nm-popup.js', $options, self::PACKAGE);
    }

    private static function requireAssets($controller, CatalogData $catalog, string $type): void
    {
        if (!$catalog->useCustomCss) {
            $controller->requireAsset('css', 'notification-manager-kit/' . $type);
        }

        $controller->requireAsset('javascript', 'notification-manager-kit/storage');
        $controller->requireAsset('javascript', 'notification-manager-kit/audience');
        $controller->requireAsset('javascript', 'notification-manager-kit/' . $type);
    }

    public static function onBeforeRender(): void
    {
        self::$bannerHtml = '';

        $page = Page::getCurrentPage();

        if (!$page || $page->isError() || $page->isAdminArea() || $page->isEditMode() || $page->isMasterCollection()) {
            return;
        }

        $catalog = Catalog::load(Localization::activeLanguage());

        if (!$catalog->isAnythingEnabled() || $catalog->isPathExcluded((string) $page->getCollectionPath())) {
            return;
        }

        $selected = Eligibility::select($catalog, (int) $page->getCollectionID());

        if ($selected['banners'] === [] && $selected['popup'] === null) {
            return;
        }

        $controller = $page->getPageController();
        self::registerAssets();

        if ($selected['banners'] !== []) {
            self::$bannerHtml = self::render('banner', ['banners' => $selected['banners']]);
            self::requireAssets($controller, $catalog, 'banner');
            self::hookOutput();
        }

        if ($selected['popups'] !== []) {
            // In priority order. With audience rules the browser keeps the first popup that applies to the visitor.
            foreach ($selected['popups'] as $popup) {
                $controller->addFooterItem(self::render('popup', ['popup' => $popup]));
            }
            self::requireAssets($controller, $catalog, 'popup');
        }
    }

    /**
     * Insert the banner straight after <body>, so it is part of the first paint instead of being added (and
     * shifting the page) by script afterwards.
     */
    public static function onPageOutput($event): void
    {
        if (self::$bannerHtml === '') {
            return;
        }

        $contents = (string) $event->getArgument('contents');
        $html = self::$bannerHtml;
        self::$bannerHtml = '';

        $count = 0;
        $updated = preg_replace_callback('/<body\b[^>]*>/i', fn($m) => $m[0] . $html, $contents, 1, $count);

        $event->setArgument('contents', $count === 1 && $updated !== null ? $updated : $contents);
    }
}
