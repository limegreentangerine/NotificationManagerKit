<?php

namespace NotificationManagerKit\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class BannerNotification extends AbstractNotification
{
    public const TRANSITIONS = [
        'fade' => 'Fade',
        'scroll-vertical' => 'Scroll vertically',
        'ticker' => 'Ticker',
    ];

    public const DEFAULT_INTERVAL = 6000;

    /**
     * @ORM\Column(type="string", length=32, nullable=true, options={"comment": "Banner only: how several banners rotate"})
     */
    protected $transition = 'fade';

    /**
     * @ORM\Column(name="intervalMs", type="integer", nullable=true, options={"comment": "Banner only: milliseconds between rotations"})
     */
    protected $interval = self::DEFAULT_INTERVAL;

    /**
     * @ORM\Column(type="boolean", nullable=true, options={"comment": "Banner only: show a dismiss button"})
     */
    protected $dismissible = false;

    public function getType(): string
    {
        return self::TYPE_BANNER;
    }

    public function duplicate(): static
    {
        $copy = parent::duplicate();

        $copy->transition = $this->transition;
        $copy->interval = $this->interval;
        $copy->dismissible = $this->dismissible;

        return $copy;
    }

    public function getTransition(): string
    {
        return isset(self::TRANSITIONS[$this->transition]) ? $this->transition : 'fade';
    }

    public function setTransition($transition): self
    {
        $this->transition = isset(self::TRANSITIONS[$transition]) ? $transition : 'fade';

        return $this;
    }

    public function getInterval(): int
    {
        return max(2000, min(60000, (int) ($this->interval ?: self::DEFAULT_INTERVAL)));
    }

    public function setInterval($interval): self
    {
        $this->interval = max(2000, min(60000, (int) $interval));

        return $this;
    }

    public function isDismissible(): bool
    {
        return (bool) $this->dismissible;
    }

    public function setDismissible($dismissible): self
    {
        $this->dismissible = (bool) $dismissible;

        return $this;
    }
}
