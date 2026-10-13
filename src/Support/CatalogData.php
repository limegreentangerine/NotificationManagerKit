<?php

namespace NotificationManagerKit\Support;

/**
 * A cacheable snapshot of the settings and every active notification for one locale.
 */
final readonly class CatalogData
{
    /**
     * @param string[]           $excludedPaths
     * @param NotificationView[] $notifications
     */
    public function __construct(
        public bool $bannersEnabled = false,
        public bool $popupsEnabled = false,
        public bool $useCustomCss = false,
        public array $excludedPaths = [],
        public array $notifications = [],
    ) {}

    public function isAnythingEnabled(): bool
    {
        return ($this->bannersEnabled || $this->popupsEnabled) && $this->notifications !== [];
    }

    public function isPathExcluded(string $path): bool
    {
        $path = strtolower('/' . trim($path, '/'));

        foreach ($this->excludedPaths as $excluded) {
            if ($path === $excluded || str_starts_with($path, $excluded . '/')) {
                return true;
            }
        }

        return false;
    }
}
