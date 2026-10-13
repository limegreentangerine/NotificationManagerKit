<?php

namespace NotificationManagerKit\Support;

/**
 * When a notification may be shown: an optional start/end window, narrowed by an optional recurrence rule.
 * A plain value object so it can be cached and unit tested without a database.
 */
final readonly class Schedule
{
    public const REPEAT_EVENTS = [
        'none' => 'Does not repeat',
        'daily' => 'Every day',
        'weekdays' => 'Weekdays (Mon-Fri)',
        'weekly_monday' => 'Every Monday',
        'monthly_fourth_monday' => 'Fourth Monday of the month',
        'annually' => 'Annually on the start date',
    ];

    public function __construct(
        public ?\DateTimeInterface $startDate = null,
        public ?\DateTimeInterface $endDate = null,
        public string $repeatEvent = 'none',
    ) {}

    public function isActiveAt(?\DateTimeInterface $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->isWithinWindow($now) && $this->matchesRecurrence($now);
    }

    public function isWithinWindow(\DateTimeInterface $now): bool
    {
        if ($this->startDate !== null && $now < $this->startDate) {
            return false;
        }

        return !($this->endDate !== null && $now > $this->endDate);
    }

    /**
     * Narrows the overall window down to particular calendar days, eg. a notification can run March-June
     * but only display on Mondays within that window.
     */
    public function matchesRecurrence(\DateTimeInterface $now): bool
    {
        if ($this->repeatEvent === 'none' || $this->startDate === null) {
            return true;
        }

        return match ($this->repeatEvent) {
            'weekdays' => (int) $now->format('N') <= 5,
            'weekly_monday' => (int) $now->format('N') === 1,
            'monthly_fourth_monday' => (int) $now->format('N') === 1 && (int) ceil((int) $now->format('j') / 7) === 4,
            'annually' => $now->format('m-d') === $this->startDate->format('m-d'),
            default => true,
        };
    }

    /**
     * The soonest moment after $now at which the answer to isActiveAt() could change, so a cached
     * decision can be given a lifetime that never outlives it. Null when nothing is scheduled to change.
     */
    public function nextChangeAfter(\DateTimeInterface $now): ?\DateTimeInterface
    {
        $candidates = [];

        foreach ([$this->startDate, $this->endDate] as $boundary) {
            if ($boundary !== null && $boundary > $now) {
                $candidates[] = $boundary;
            }
        }

        // Recurrence rules are decided per calendar day, so they can change at the next midnight.
        if ($this->repeatEvent !== 'none' && $this->startDate !== null) {
            $candidates[] = \DateTimeImmutable::createFromInterface($now)->modify('tomorrow');
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn($a, $b) => $a <=> $b);

        return $candidates[0];
    }

    public static function isValidRepeatEvent(string $repeatEvent): bool
    {
        return isset(self::REPEAT_EVENTS[$repeatEvent]);
    }
}
