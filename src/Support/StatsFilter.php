<?php

namespace NotificationManagerKit\Support;

/**
 * What the statistics page and its exports are asked to show: a range of days, a notification type and
 * optionally one notification. Built from the query string, which is untrusted, so anything unrecognised
 * falls back to a default rather than being used. Pure: no database, no Concrete.
 */
final readonly class StatsFilter
{
    public const TYPES = ['all', 'banner', 'popup'];
    public const PRESETS = [7, 30, 90];
    public const DEFAULT_DAYS = 30;

    /** The longest range allowed: the longest the statistics can be kept for. */
    public const MAX_DAYS = 730;

    private function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public string $type,
        public string $notificationId,
        public ?int $preset,
    ) {}

    /**
     * The first day of a window of $days days ending on $end.
     */
    private static function back(\DateTimeImmutable $end, int $days): \DateTimeImmutable
    {
        return $end->modify('-' . ($days - 1) . ' days');
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        // createFromFormat rolls 2026-02-31 over to March; only a date that survives a round trip is real.
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * @param array<string, mixed> $query range (7, 30 or 90), or from and to (Y-m-d); type; notification (an id)
     */
    public static function fromRequest(array $query, \DateTimeInterface $today): self
    {
        $today = \DateTimeImmutable::createFromInterface($today)->setTime(0, 0);

        $range = $query['range'] ?? null;
        $preset = is_string($range) && in_array((int) $range, self::PRESETS, true) && (string) (int) $range === $range ? (int) $range : null;

        $from = $to = null;

        if ($preset !== null) {
            $to = $today;
            $from = self::back($to, $preset);
        } else {
            $from = self::date($query['from'] ?? null);
            $to = self::date($query['to'] ?? null);

            if ($from === null && $to === null) {
                $preset = self::DEFAULT_DAYS;
                $to = $today;
                $from = self::back($to, $preset);
            } else {
                $to = $to === null ? $today : min($to, $today);
                $from = $from ?? self::back($to, self::DEFAULT_DAYS);

                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }

                $earliest = self::back($to, self::MAX_DAYS);
                if ($from < $earliest) {
                    $from = $earliest;
                }
            }
        }

        $type = is_string($query['type'] ?? null) && in_array($query['type'], self::TYPES, true) ? $query['type'] : 'all';
        $id = is_string($query['notification'] ?? null) && TrackRequest::isValidId($query['notification']) ? strtolower($query['notification']) : '';

        return new self($from, $to, $type, $id, $preset);
    }

    /**
     * Days in the range, both ends included.
     */
    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /**
     * The query string that rebuilds this filter, for links and exports. Defaults are left out.
     *
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        $query = $this->preset !== null
            ? ['range' => $this->preset]
            : ['from' => $this->from->format('Y-m-d'), 'to' => $this->to->format('Y-m-d')];

        if ($this->type !== 'all') {
            $query['type'] = $this->type;
        }

        if ($this->notificationId !== '') {
            $query['notification'] = $this->notificationId;
        }

        return $query;
    }
}
