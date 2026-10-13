<?php

namespace NotificationManagerKit\Entity;

use Doctrine\ORM\Mapping as ORM;
use NotificationManagerKit\Support\SelectorValidator;

/**
 * @ORM\Entity
 */
class PopupNotification extends AbstractNotification
{
    public const FREQUENCIES = [
        'every_time' => 'Every page view',
        'every_time_until_close' => 'Every visit, until closed',
        'once' => 'Once only',
        'daily' => 'Once a day',
        'weekly' => 'Once a week',
    ];

    public const SCROLL_UNITS = [
        'percent' => 'Percent of page',
        'px' => 'Pixels',
    ];

    /**
     * @ORM\Column(type="string", length=32, nullable=true, options={"comment": "Popup only: how often a visitor sees it"})
     */
    protected $frequency = 'every_time_until_close';

    /**
     * @ORM\Column(type="boolean", nullable=true, options={"comment": "Popup only: open on page load"})
     */
    protected $triggerOnLoad = true;

    /**
     * @ORM\Column(type="integer", nullable=true, options={"comment": "Popup only: delay before opening on load (ms)"})
     */
    protected $triggerDelayMs = 0;

    /**
     * @ORM\Column(type="boolean", nullable=true, options={"comment": "Popup only: open after scrolling"})
     */
    protected $triggerOnScroll = false;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $triggerScrollValue = 50;

    /**
     * @ORM\Column(type="string", length=8, nullable=true)
     */
    protected $triggerScrollUnit = 'percent';

    /**
     * @ORM\Column(type="boolean", nullable=true, options={"comment": "Popup only: open when an element scrolls into view"})
     */
    protected $triggerOnScrollToElement = false;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $triggerScrollElementSelector;

    /**
     * @ORM\Column(type="boolean", nullable=true, options={"comment": "Popup only: open when an element is clicked"})
     */
    protected $triggerOnClick = false;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $triggerClickSelector;

    public function getType(): string
    {
        return self::TYPE_POPUP;
    }

    public function getFrequency(): string
    {
        return isset(self::FREQUENCIES[$this->frequency]) ? $this->frequency : 'every_time_until_close';
    }

    public function setFrequency($frequency): self
    {
        $this->frequency = isset(self::FREQUENCIES[$frequency]) ? $frequency : 'every_time_until_close';

        return $this;
    }

    public function isTriggerOnLoad(): bool
    {
        return (bool) $this->triggerOnLoad;
    }

    public function setTriggerOnLoad($value): self
    {
        $this->triggerOnLoad = (bool) $value;

        return $this;
    }

    public function getTriggerDelayMs(): int
    {
        return max(0, min(60000, (int) $this->triggerDelayMs));
    }

    public function setTriggerDelayMs($value): self
    {
        $this->triggerDelayMs = max(0, min(60000, (int) $value));

        return $this;
    }

    public function isTriggerOnScroll(): bool
    {
        return (bool) $this->triggerOnScroll;
    }

    public function setTriggerOnScroll($value): self
    {
        $this->triggerOnScroll = (bool) $value;

        return $this;
    }

    public function getTriggerScrollUnit(): string
    {
        return isset(self::SCROLL_UNITS[$this->triggerScrollUnit]) ? $this->triggerScrollUnit : 'percent';
    }

    public function setTriggerScrollUnit($unit): self
    {
        $this->triggerScrollUnit = isset(self::SCROLL_UNITS[$unit]) ? $unit : 'percent';

        return $this;
    }

    public function getTriggerScrollValue(): int
    {
        $max = $this->getTriggerScrollUnit() === 'percent' ? 100 : 100000;

        return max(0, min($max, (int) $this->triggerScrollValue));
    }

    public function setTriggerScrollValue($value): self
    {
        $this->triggerScrollValue = max(0, min(100000, (int) $value));

        return $this;
    }

    public function isTriggerOnScrollToElement(): bool
    {
        return (bool) $this->triggerOnScrollToElement;
    }

    public function setTriggerOnScrollToElement($value): self
    {
        $this->triggerOnScrollToElement = (bool) $value;

        return $this;
    }

    public function getTriggerScrollElementSelector(): string
    {
        return (string) SelectorValidator::sanitize($this->triggerScrollElementSelector);
    }

    public function setTriggerScrollElementSelector(?string $selector): self
    {
        $this->triggerScrollElementSelector = SelectorValidator::sanitize($selector);

        return $this;
    }

    public function isTriggerOnClick(): bool
    {
        return (bool) $this->triggerOnClick;
    }

    public function setTriggerOnClick($value): self
    {
        $this->triggerOnClick = (bool) $value;

        return $this;
    }

    public function getTriggerClickSelector(): string
    {
        return (string) SelectorValidator::sanitize($this->triggerClickSelector);
    }

    public function setTriggerClickSelector(?string $selector): self
    {
        $this->triggerClickSelector = SelectorValidator::sanitize($selector);

        return $this;
    }

    /**
     * Whether at least one trigger is configured to a usable state. A popup with none can never open.
     */
    public function hasUsableTrigger(): bool
    {
        return $this->isTriggerOnLoad()
            || $this->isTriggerOnScroll()
            || ($this->isTriggerOnScrollToElement() && $this->getTriggerScrollElementSelector() !== '')
            || ($this->isTriggerOnClick() && $this->getTriggerClickSelector() !== '');
    }
}
