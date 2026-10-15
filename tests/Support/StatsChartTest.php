<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Support\StatsChart;
use PHPUnit\Framework\Attributes\DataProvider;

final class StatsChartTest extends TestCase
{
    /**
     * @return array<string, int>
     */
    private function series(string $start, int $days, callable $value): array
    {
        $series = [];
        $date = new \DateTimeImmutable($start);

        for ($i = 0; $i < $days; $i++) {
            $series[$date->modify("+{$i} days")->format('Y-m-d')] = $value($i);
        }

        return $series;
    }

    public function testOneBarPerDayForAShortRange(): void
    {
        $chart = StatsChart::build(['2026-10-13' => 10, '2026-10-14' => 0, '2026-10-15' => 40]);

        $this->assertFalse($chart['grouped']);
        $this->assertSame(['2026-10-13', '2026-10-14', '2026-10-15'], array_column($chart['bars'], 'label'));
        $this->assertSame([10, 0, 40], array_column($chart['bars'], 'value'));
    }

    public function testHeightsAreAFractionOfTheAxisMaximum(): void
    {
        $chart = StatsChart::build(['2026-10-14' => 0, '2026-10-15' => 40]);

        $this->assertSame(0.0, $chart['bars'][0]['height']);
        $this->assertEqualsWithDelta(40 / $chart['max'], $chart['bars'][1]['height'], 0.00001);
        $this->assertLessThanOrEqual(1.0, $chart['bars'][1]['height']);
    }

    #[DataProvider('axes')]
    public function testTheAxisUsesRoundNumbers(int $peak, int $max, array $ticks): void
    {
        $chart = StatsChart::build(['2026-10-15' => $peak]);

        $this->assertSame($max, $chart['max']);
        $this->assertSame($ticks, $chart['ticks']);
        $this->assertGreaterThanOrEqual($peak, $chart['max']);
    }

    public static function axes(): array
    {
        return [
            'nothing' => [0, 1, [0, 1]],
            'one' => [1, 1, [0, 1]],
            'three' => [3, 3, [0, 1, 2, 3]],
            'ten' => [10, 10, [0, 2, 4, 6, 8, 10]],
            '532' => [532, 600, [0, 200, 400, 600]],
            'exactly round' => [500, 500, [0, 100, 200, 300, 400, 500]],
            '1234' => [1234, 1500, [0, 500, 1000, 1500]],
            'large' => [98765, 100000, [0, 20000, 40000, 60000, 80000, 100000]],
        ];
    }

    public function testAnAllZeroRangeStillHasAnAxisAndNoBarHeight(): void
    {
        $chart = StatsChart::build(['2026-10-14' => 0, '2026-10-15' => 0]);

        $this->assertSame(1, $chart['max']);
        $this->assertSame([0.0, 0.0], array_column($chart['bars'], 'height'));
    }

    public function testAnEmptySeriesIsEmpty(): void
    {
        $chart = StatsChart::build([]);

        $this->assertSame([], $chart['bars']);
        $this->assertSame(1, $chart['max']);
    }

    public function testALongRangeIsGroupedIntoWeeks(): void
    {
        // 2026-01-05 is a Monday; 140 days is exactly 20 weeks.
        $chart = StatsChart::build($this->series('2026-01-05', 140, fn() => 1));

        $this->assertTrue($chart['grouped']);
        $this->assertCount(20, $chart['bars']);
        $this->assertSame(7, $chart['bars'][0]['value']);
        $this->assertSame('Week of 2026-01-05', $chart['bars'][0]['label']);
        $this->assertSame('Week of 2026-01-12', $chart['bars'][1]['label']);
    }

    public function testGroupingKeepsTheTotal(): void
    {
        $series = $this->series('2026-01-07', 200, fn(int $i) => $i % 5);
        $chart = StatsChart::build($series);

        $this->assertTrue($chart['grouped']);
        $this->assertSame(array_sum($series), array_sum(array_column($chart['bars'], 'value')));
    }

    public function testAPartialFirstWeekIsLabelledWithItsFirstDay(): void
    {
        // 2026-01-07 is a Wednesday, so the first week has five days in range.
        $chart = StatsChart::build($this->series('2026-01-07', 200, fn() => 1));

        $this->assertSame('Week of 2026-01-07', $chart['bars'][0]['label']);
        $this->assertSame(5, $chart['bars'][0]['value']);
        $this->assertSame('Week of 2026-01-12', $chart['bars'][1]['label']);
    }

    public function testTheLongestRangeStaysWithinTheBarLimit(): void
    {
        $chart = StatsChart::build($this->series('2024-10-17', 730, fn() => 1));

        $this->assertTrue($chart['grouped']);
        $this->assertLessThanOrEqual(StatsChart::MAX_BARS, count($chart['bars']));
    }

    public function testTheLimitItselfIsStillDaily(): void
    {
        $chart = StatsChart::build($this->series('2026-01-01', StatsChart::MAX_BARS, fn() => 1));

        $this->assertFalse($chart['grouped']);
        $this->assertCount(StatsChart::MAX_BARS, $chart['bars']);
    }

    #[DataProvider('compact')]
    public function testCompactNumbers(int $number, string $expected): void
    {
        $this->assertSame($expected, StatsChart::compact($number));
    }

    public static function compact(): array
    {
        return [
            [0, '0'],
            [999, '999'],
            [1000, '1K'],
            [1500, '1.5K'],
            [20000, '20K'],
            [100000, '100K'],
            [1000000, '1M'],
            [2500000, '2.5M'],
        ];
    }
}
