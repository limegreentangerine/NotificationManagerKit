<?php

namespace NotificationManagerKit\Support;

/**
 * Turns a report into what the exports contain: the JSON document, and the rows and headers for the Excel and PDF
 * writers. Pure, so what goes into each format is tested without touching a library or the browser. Headers and
 * keys are English on purpose: an export is data, and its columns shouldn't change with the dashboard's language.
 */
final class StatsExport
{
    private const TYPE_LABELS = ['banner' => 'Banner', 'popup' => 'Popup'];
    public const FORMATS = ['xlsx', 'json', 'pdf'];

    private static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? $type;
    }

    private static function round(?float $rate): ?float
    {
        return $rate === null ? null : round($rate, 4);
    }

    public static function isFormat(string $format): bool
    {
        return in_array($format, self::FORMATS, true);
    }

    public static function filename(StatsReport $report, string $extension): string
    {
        return sprintf('notification-statistics-%s_to_%s.%s', $report->filter->from->format('Y-m-d'), $report->filter->to->format('Y-m-d'), $extension);
    }

    public static function json(StatsReport $report, \DateTimeInterface $generated): string
    {
        $filter = $report->filter;
        $totals = $report->totals;

        return (string) json_encode([
            'generated' => $generated->format(\DateTimeInterface::ATOM),
            'range' => ['from' => $filter->from->format('Y-m-d'), 'to' => $filter->to->format('Y-m-d'), 'days' => $filter->days()],
            'filters' => ['type' => $filter->type, 'notification' => $filter->notificationId === '' ? null : $filter->notificationId],
            'totals' => [
                'impressions' => $totals->impressions,
                'clicks' => $totals->clicks,
                'clickRate' => self::round($totals->clickRate()),
                'dismissals' => $totals->dismissals,
                'dismissalRate' => self::round($totals->dismissalRate()),
            ],
            'notifications' => array_map(fn(array $n) => [
                'id' => $n['id'],
                'name' => $n['name'],
                'type' => $n['type'],
                'impressions' => $n['impressions'],
                'clicks' => $n['clicks'],
                'clickRate' => self::round($n['clickRate']),
                'dismissals' => $n['dismissals'],
                'dismissalRate' => self::round($n['dismissalRate']),
            ], $report->notifications),
            'daily' => $report->dailyRows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    /**
     * One row per notification and a total. Rates are fractions (0.025 = 2.5%) or null, left for the writer to
     * format, so a spreadsheet gets a real number it can sort and calculate with.
     *
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>, total: list<string|int|float|null>}
     */
    public static function summary(StatsReport $report): array
    {
        $totals = $report->totals;

        return [
            'headers' => ['Notification', 'Type', 'Shown', 'Clicks', 'Click rate', 'Dismissed', 'Dismissal rate'],
            'rows' => array_map(fn(array $n) => [
                $n['name'],
                self::typeLabel($n['type']),
                $n['impressions'],
                $n['clicks'],
                $n['clickRate'],
                $n['dismissals'],
                $n['dismissalRate'],
            ], $report->notifications),
            'total' => ['Total', '', $totals->impressions, $totals->clicks, $totals->clickRate(), $totals->dismissals, $totals->dismissalRate()],
        ];
    }

    /**
     * One row per notification per day that had any activity.
     *
     * @return array{headers: list<string>, rows: list<list<string|int>>}
     */
    public static function daily(StatsReport $report): array
    {
        return [
            'headers' => ['Date', 'Notification', 'Type', 'Shown', 'Clicks', 'Dismissed'],
            'rows' => array_map(fn(array $row) => [
                $row['date'],
                $row['name'],
                self::typeLabel($row['type']),
                $row['impressions'],
                $row['clicks'],
                $row['dismissals'],
            ], $report->dailyRows),
        ];
    }
}
