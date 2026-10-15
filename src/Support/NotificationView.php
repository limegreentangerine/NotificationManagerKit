<?php

namespace NotificationManagerKit\Support;

/**
 * Everything the front end needs to know about one notification in one locale, as plain data.
 * This is what gets cached, so a page view never has to hydrate entities.
 */
final readonly class NotificationView
{
    /**
     * @param int[]                                                         $pageIds  Only used when $scope is 'specific'
     * @param array{url: string, width: int, height: int, alt: string}|null $image
     * @param array<string, scalar>                                         $options  Type specific, already validated (transition, interval, triggers, ...)
     * @param array<string, mixed>                                          $audience Audience::toArray(); empty means everyone. Checked in the browser, never here
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $priority,
        public int $version,
        public int $created,
        public Schedule $schedule,
        public string $scope,
        public array $pageIds,
        public string $name,
        public string $contentHtml,
        public string $linkUrl,
        public string $linkText,
        public ?array $image,
        public array $options,
        public array $audience = [],
    ) {}

    public function isVisibleOnPage(int $cID): bool
    {
        return $this->scope !== 'specific' || in_array($cID, $this->pageIds, true);
    }
}
