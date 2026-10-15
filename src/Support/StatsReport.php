<?php

namespace NotificationManagerKit\Support;

/**
 * What the statistics page and its exports show for one filter: a total for each notification, the overall daily
 * trend, and the day by day figures behind them. Pure: it is built from notifications and counter rows the store
 * has already read, so the page, the Excel file, the JSON and the PDF can never disagree.
 */
final readonly class StatsReport
{
    /**
     * @param list<array{id: string, name: string, type: string, impressions: int, clicks: int, dismissals: int, clickRate: float|null, dismissalRate: float|null}> $notifications Busiest first
     * @param list<array{date: string, id: string, name: string, type: string, impressions: int, clicks: int, dismissals: int}>                                     $dailyRows     Days with activity only, oldest first
     */
    private function __construct(
        public StatsFilter $filter,
        public array $notifications,
        public StatsSummary $totals,
        public array $dailyRows,
    ) {}

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * @param array<string, array{name: string, type: string}> $notifications The notifications the filter selects, by id
     * @param iterable<array<string, mixed>>                   $rows          notificationId, statDate, impressions, clicks, dismissals; one per day and language
     */
    public static function build(StatsFilter $filter, array $notifications, iterable $rows): self
    {
        $first = $filter->from->format('Y-m-d');
        $last = $filter->to->format('Y-m-d');

        $perNotification = [];
        $perDay = [];
        $kept = [];

        foreach ($rows as $row) {
            $id = (string) ($row['notificationId'] ?? '');
            $date = (string) ($row['statDate'] ?? '');

            if (!isset($notifications[$id]) || $date < $first || $date > $last) {
                continue;
            }

            $counts = [
                'impressions' => self::count($row['impressions'] ?? 0),
                'clicks' => self::count($row['clicks'] ?? 0),
                'dismissals' => self::count($row['dismissals'] ?? 0),
            ];

            $kept[] = ['statDate' => $date] + $counts;

            foreach ($counts as $field => $value) {
                $perNotification[$id][$field] = ($perNotification[$id][$field] ?? 0) + $value;
                $perDay[$date][$id][$field] = ($perDay[$date][$id][$field] ?? 0) + $value;
            }
        }

        $summaries = [];
        foreach ($notifications as $id => $meta) {
            $impressions = $perNotification[$id]['impressions'] ?? 0;

            $summaries[] = [
                'id' => (string) $id,
                'name' => $meta['name'],
                'type' => $meta['type'],
                'impressions' => $impressions,
                'clicks' => $perNotification[$id]['clicks'] ?? 0,
                'dismissals' => $perNotification[$id]['dismissals'] ?? 0,
                'clickRate' => StatsSummary::rate($perNotification[$id]['clicks'] ?? 0, $impressions),
                'dismissalRate' => StatsSummary::rate($perNotification[$id]['dismissals'] ?? 0, $impressions),
            ];
        }

        usort($summaries, fn(array $a, array $b) => [$b['impressions'], $b['clicks'], strtolower($a['name']), $a['id']] <=> [$a['impressions'], $a['clicks'], strtolower($b['name']), $b['id']]);

        ksort($perDay);
        $dailyRows = [];
        foreach ($perDay as $date => $byNotification) {
            $rowsForDay = [];

            foreach ($byNotification as $id => $counts) {
                $counts += ['impressions' => 0, 'clicks' => 0, 'dismissals' => 0];

                if ($counts['impressions'] + $counts['clicks'] + $counts['dismissals'] === 0) {
                    continue;
                }

                $rowsForDay[] = [
                    'date' => (string) $date,
                    'id' => (string) $id,
                    'name' => $notifications[$id]['name'],
                    'type' => $notifications[$id]['type'],
                    'impressions' => $counts['impressions'],
                    'clicks' => $counts['clicks'],
                    'dismissals' => $counts['dismissals'],
                ];
            }

            usort($rowsForDay, fn(array $a, array $b) => [strtolower($a['name']), $a['id']] <=> [strtolower($b['name']), $b['id']]);
            array_push($dailyRows, ...$rowsForDay);
        }

        return new self($filter, $summaries, StatsSummary::fromRows($kept, $filter->to, $filter->days()), $dailyRows);
    }

    public function hasActivity(): bool
    {
        return $this->totals->impressions + $this->totals->clicks + $this->totals->dismissals > 0;
    }
}
