<?php

namespace NotificationManagerKit\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * How often a notification was shown, clicked and dismissed on one day in one language. Counters only: nothing
 * here identifies a visitor. Rows are written with an atomic upsert (Support\StatsStore), so this class exists
 * to describe the table to Doctrine, and the foreign key makes deleting a notification delete its figures.
 *
 * @ORM\Entity
 * @ORM\Table(
 *      name="nmNotificationStats",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(name="uniq_nm_stat_day", columns={"notificationId", "statDate", "language"})
 *      },
 *      indexes={
 *          @ORM\Index(name="idx_nm_stat_date", columns={"statDate"})
 *      },
 *      options={"comment": "Daily impression, click and dismissal counts per notification and language"}
 * )
 */
class NotificationStat
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="NotificationManagerKit\Entity\AbstractNotification")
     * @ORM\JoinColumn(name="notificationId", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $notification;

    /**
     * @ORM\Column(name="statDate", type="date_immutable", nullable=false)
     */
    protected $statDate;

    /**
     * @ORM\Column(type="string", length=32, nullable=false, options={"comment": "Locale language code"})
     */
    protected $language;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "unsigned": true})
     */
    protected $impressions = 0;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "unsigned": true})
     */
    protected $clicks = 0;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "unsigned": true})
     */
    protected $dismissals = 0;

    public function getID(): ?int
    {
        return $this->id;
    }

    public function getStatDate(): \DateTimeImmutable
    {
        return $this->statDate;
    }

    public function getLanguage(): string
    {
        return (string) $this->language;
    }

    public function getImpressions(): int
    {
        return (int) $this->impressions;
    }

    public function getClicks(): int
    {
        return (int) $this->clicks;
    }

    public function getDismissals(): int
    {
        return (int) $this->dismissals;
    }
}
