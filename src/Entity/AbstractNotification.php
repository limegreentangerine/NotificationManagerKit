<?php

namespace NotificationManagerKit\Entity;

use Doctrine\ORM\Mapping as ORM;
use ClassKit\Entity\Core\UpdatedGuidEntity;
use NotificationManagerKit\Support\{Audience, Schedule};
use Doctrine\Common\Collections\{ArrayCollection, Collection};

/**
 * A banner or a popup (single table inheritance, discriminated by `type`).
 * Everything shared (schedule, targeting, content) lives here; type specific columns live on the subclasses
 * and are nullable at the database level, so required-ness is enforced by validation instead.
 *
 * @phpstan-consistent-constructor
 *
 * @ORM\Entity
 * @ORM\Table(
 *      name="nmNotifications",
 *      indexes={
 *          @ORM\Index(name="idx_nm_type_active", columns={"type", "active"})
 *      },
 *      options={"comment": "Banner and popup notifications"}
 * )
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string", length=16)
 * @ORM\DiscriminatorMap({
 *      "banner" = "NotificationManagerKit\Entity\BannerNotification",
 *      "popup" = "NotificationManagerKit\Entity\PopupNotification"
 * })
 */
abstract class AbstractNotification extends UpdatedGuidEntity
{
    public const TYPE_BANNER = 'banner';
    public const TYPE_POPUP = 'popup';

    public const MIN_PRIORITY = -1000;
    public const MAX_PRIORITY = 1000;

    public const DISPLAY_SCOPES = [
        'all' => 'All Pages',
        'specific' => 'Specific Pages',
    ];

    /**
     * @ORM\ManyToMany(targetEntity="NotificationManagerKit\Entity\NotificationContent", cascade={"persist"})
     * @ORM\JoinTable(name="nmNotification_Contents",
     *      joinColumns={
     *          @ORM\JoinColumn(name="notificationId", referencedColumnName="id", onDelete="cascade")
     *      },
     *      inverseJoinColumns={
     *          @ORM\JoinColumn(name="notificationContentId", referencedColumnName="id", onDelete="cascade")
     *      }
     * )
     */
    protected $data;

    /**
     * @ORM\OneToMany(targetEntity="NotificationManagerKit\Entity\NotificationPage", mappedBy="notification", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $pages;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default": true, "comment": "Whether this notification is eligible to display"})
     */
    protected $active = true;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "comment": "Higher wins when more than one popup is eligible; orders banners"})
     */
    protected $priority = 0;

    /**
     * @ORM\Column(type="datetime", nullable=true, options={"comment": "Will not display before this date/time"})
     */
    protected $startDate;

    /**
     * @ORM\Column(type="datetime", nullable=true, options={"comment": "Will not display after this date/time"})
     */
    protected $endDate;

    /**
     * @ORM\Column(type="string", length=32, nullable=false, options={"default": "none", "comment": "Recurrence rule narrowing the start/end window"})
     */
    protected $repeatEvent = 'none';

    /**
     * @ORM\Column(type="string", length=16, nullable=false, options={"default": "all", "comment": "all or specific pages"})
     */
    protected $displayScope = 'all';

    /**
     * @ORM\Column(name="audienceRules", type="text", nullable=true, options={"comment": "JSON audience rules evaluated in the browser; null means everyone"})
     */
    protected $audience;

    public function __construct()
    {
        parent::__construct();
        $this->data = new ArrayCollection();
        $this->pages = new ArrayCollection();
    }

    abstract public function getType(): string;

    /**
     * A new, unsaved notification of the given type, or null for an unknown type.
     */
    public static function create(string $type): ?self
    {
        return match ($type) {
            self::TYPE_BANNER => new BannerNotification(),
            self::TYPE_POPUP => new PopupNotification(),
            default => null,
        };
    }

    /**
     * An unsaved, inactive copy: same schedule, targeting and per-locale content, with its own content rows.
     * Subclasses extend this to carry their type specific settings.
     */
    public function duplicate(): static
    {
        $copy = new static();

        $copy->active = false;
        $copy->priority = $this->priority;
        $copy->startDate = $this->startDate === null ? null : \DateTime::createFromInterface($this->startDate);
        $copy->endDate = $this->endDate === null ? null : \DateTime::createFromInterface($this->endDate);
        $copy->repeatEvent = $this->repeatEvent;
        $copy->displayScope = $this->displayScope;
        $copy->audience = $this->audience;
        $copy->setPageIds($this->getPageIds());

        foreach ($this->data as $content) {
            $copy->addData($content->duplicate());
        }

        return $copy;
    }

    public function getTypeString(): string
    {
        return $this->getType() === self::TYPE_POPUP ? t('Popup') : t('Banner');
    }

    /**
     * @return Collection<int, NotificationContent>
     */
    public function getData(): Collection
    {
        return $this->data;
    }

    /**
     * The content row for a locale, or false.
     * Loops rather than matching(): on an uninitialised collection matching() runs a query on every call.
     */
    public function getContent(string $language): NotificationContent|false
    {
        foreach ($this->data as $content) {
            if ($content->getLanguage() === $language) {
                return $content;
            }
        }

        return false;
    }

    public function getName(string $language = 'en'): string
    {
        $content = $this->getContent($language);

        return $content ? (string) $content->getName() : '';
    }

    /**
     * A name to show in lists: the first locale that has one.
     */
    public function getAnyName(): string
    {
        foreach ($this->data as $content) {
            if ($content->getName() !== '') {
                return $content->getName();
            }
        }

        return '';
    }

    public function getStatusString(?\DateTimeInterface $now = null): string
    {
        return match (true) {
            !$this->active => t('Inactive'),
            $this->isWithinSchedule($now) => t('Live'),
            default => t('Scheduled'),
        };
    }

    public function addData(NotificationContent $content): self
    {
        if (!$this->data->contains($content)) {
            $this->data->add($content);
        }

        return $this;
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function setActive($active): self
    {
        $this->active = (bool) $active;

        return $this;
    }

    /**
     * Whether a submitted priority is acceptable: blank (meaning 0), or a whole number within the allowed range.
     * setPriority() still clamps, so a stored value can never be out of range even if this check is skipped.
     */
    public static function isValidPriority(mixed $priority): bool
    {
        if ($priority === null || $priority === '') {
            return true;
        }

        if (is_int($priority) || (is_string($priority) && preg_match('/^-?\d+$/', trim($priority)))) {
            return (int) $priority >= self::MIN_PRIORITY && (int) $priority <= self::MAX_PRIORITY;
        }

        return false;
    }

    public function getPriority(): int
    {
        return (int) $this->priority;
    }

    public function setPriority($priority): self
    {
        $this->priority = max(self::MIN_PRIORITY, min(self::MAX_PRIORITY, (int) $priority));

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getRepeatEvent(): string
    {
        return $this->repeatEvent;
    }

    public function setRepeatEvent($repeatEvent): self
    {
        $this->repeatEvent = Schedule::isValidRepeatEvent((string) $repeatEvent) ? (string) $repeatEvent : 'none';

        return $this;
    }

    public function getSchedule(): Schedule
    {
        return new Schedule($this->startDate, $this->endDate, $this->repeatEvent);
    }

    public function isWithinSchedule(?\DateTimeInterface $now = null): bool
    {
        return $this->getSchedule()->isActiveAt($now);
    }

    public function getDisplayScope(): string
    {
        return $this->displayScope;
    }

    public function getDisplayScopeString(): string
    {
        return t(self::DISPLAY_SCOPES[$this->displayScope] ?? 'All Pages');
    }

    public function setDisplayScope($displayScope): self
    {
        $this->displayScope = isset(self::DISPLAY_SCOPES[$displayScope]) ? $displayScope : 'all';

        return $this;
    }

    public function getAudience(): Audience
    {
        return Audience::fromJson($this->audience);
    }

    public function setAudience(Audience $audience): self
    {
        $this->audience = $audience->toJson();

        return $this;
    }

    /**
     * @return int[]
     */
    public function getPageIds(): array
    {
        return array_values(array_map(fn(NotificationPage $page) => $page->getCID(), $this->pages->toArray()));
    }

    /**
     * Replace the targeted pages with exactly these cIDs.
     *
     * @param int[] $cIDs
     */
    public function setPageIds(array $cIDs): self
    {
        $wanted = array_values(array_unique(array_filter(array_map('intval', $cIDs), fn(int $cID) => $cID > 0)));

        foreach ($this->pages->toArray() as $page) {
            if (!in_array($page->getCID(), $wanted, true)) {
                $this->pages->removeElement($page);
            }
        }

        $existing = $this->getPageIds();
        foreach ($wanted as $cID) {
            if (!in_array($cID, $existing, true)) {
                $this->pages->add((new NotificationPage())->setNotification($this)->setCID($cID));
            }
        }

        return $this;
    }

    public function isVisibleOnPageId(int $cID): bool
    {
        return $this->displayScope !== 'specific' || in_array($cID, $this->getPageIds(), true);
    }
}
