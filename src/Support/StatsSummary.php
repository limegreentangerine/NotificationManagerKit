<?php

namespace NotificationManagerKit\Support;

/**
 * A notification's counters over a window of days, with a figure for every day (zeroes included) so a report
 * never has gaps. Pure: it is built from rows the store has already read.
 */
final readonly class StatsSummary
{
    /**
     * @param array<string, array{impressions: int, clicks: int, dismissals: int}> $daily Y-m-d => counts, oldest first
     */
    private function __construct(
        public int $impressions,
        public int $clicks,
        public int $dismissals,
        public array $daily,
    ) {}

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * @param iterable<array<string, mixed>> $rows statDate, impressions, clicks and dismissals; one per day and language
     */
    public static function fromRows(iterable $rows, \DateTimeInterface $today, int $days): self
    {
        $days = max(1, $days);
        $end = \DateTimeImmutable::createFromInterface($today)->setTime(0, 0);

        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $daily[$end->modify("-{$i} days")->format('Y-m-d')] = ['impressions' => 0, 'clicks' => 0, 'dismissals' => 0];
        }

        foreach ($rows as $row) {
            $date = (string) ($row['statDate'] ?? '');

            if (!isset($daily[$date])) {
                continue;
            }

            foreach (['impressions', 'clicks', 'dismissals'] as $field) {
                $daily[$date][$field] += self::count($row[$field] ?? 0);
            }
        }

        return new self(
            array_sum(array_column($daily, 'impressions')),
            array_sum(array_column($daily, 'clicks')),
            array_sum(array_column($daily, 'dismissals')),
            $daily,
        );
    }

    /**
     * Clicks as a fraction of impressions, or null when nothing was shown. Capped at 1: counters from different
     * page views can briefly disagree, and a rate above 100% would only confuse.
     */
    public function clickRate(): ?float
    {
        return $this->impressions > 0 ? min(1.0, $this->clicks / $this->impressions) : null;
    }

    public function dismissalRate(): ?float
    {
        return $this->impressions > 0 ? min(1.0, $this->dismissals / $this->impressions) : null;
    }

    public static function percent(?float $fraction): string
    {
        if ($fraction === null) {
            return '–';
        }

        return rtrim(rtrim(number_format($fraction * 100, 1, '.', ''), '0'), '.') . '%';
    }
}
