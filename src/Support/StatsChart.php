<?php

namespace NotificationManagerKit\Support;

/**
 * The shape of a daily bar chart, worked out in PHP so it can be tested and drawn by a plain view: bar heights
 * as a fraction of a round-numbered axis, and weekly bars when a long range would otherwise be hundreds of
 * hairline columns. No drawing happens here.
 */
final class StatsChart
{
    /** More days than this are grouped into weeks. */
    public const MAX_BARS = 110;

    /**
     * Days added up per week (Monday to Sunday). A week is named by its first day in the range, so a partial first
     * week is labelled with the day it actually starts on.
     *
     * @param array<string, int> $series
     *
     * @return array<string, int>
     */
    private static function weekly(array $series): array
    {
        $weeks = [];
        $current = null;

        foreach ($series as $date => $value) {
            $day = new \DateTimeImmutable($date);

            if ($current === null || $day->format('N') === '1') {
                $current = $date;
            }

            $weeks[$current] = ($weeks[$current] ?? 0) + $value;
        }

        return $weeks;
    }

    /**
     * The smallest round gap between axis ticks (1, 2 or 5 times a power of ten) that needs no more than five
     * intervals to reach the peak, so the axis ends close to the data rather than far above it.
     */
    private static function step(int $peak): int
    {
        $peak = max(1, $peak);

        for ($power = 1; ; $power *= 10) {
            foreach ([1, 2, 5] as $factor) {
                $step = $factor * $power;

                if (ceil($peak / $step) <= 5) {
                    return $step;
                }
            }
        }
    }

    /**
     * @param array<string, int> $series Y-m-d => value, oldest first
     *
     * @return array{bars: list<array{label: string, value: int, height: float}>, max: int, ticks: list<int>, grouped: bool}
     */
    public static function build(array $series): array
    {
        $grouped = count($series) > self::MAX_BARS;
        $points = $grouped ? self::weekly($series) : $series;

        $peak = $points === [] ? 0 : max($points);
        $step = self::step($peak);
        $max = max(1, (int) (ceil($peak / $step) * $step));

        $bars = [];
        foreach ($points as $label => $value) {
            $bars[] = [
                'label' => $grouped ? 'Week of ' . $label : (string) $label,
                'value' => $value,
                'height' => (float) ($value / $max),
            ];
        }

        return ['bars' => $bars, 'max' => $max, 'ticks' => range(0, $max, $step), 'grouped' => $grouped];
    }

    /**
     * 1,000 -> 1K, 1,500 -> 1.5K, 2,500,000 -> 2.5M; smaller numbers as they are.
     */
    public static function compact(int $number): string
    {
        foreach ([1000000 => 'M', 1000 => 'K'] as $size => $suffix) {
            if ($number >= $size) {
                return rtrim(rtrim(number_format($number / $size, 1, '.', ''), '0'), '.') . $suffix;
            }
        }

        return (string) $number;
    }
}
