<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Support\{StatsFilter, StatsReport, StatsSummary};

final class StatsReportTest extends TestCase
{
    private const A = '441a281e-c719-11f1-bd37-cfbc4319b53f';
    private const B = '60149570-c71b-11f1-bd37-cfbc4319b53f';
    private const C = '67ab3af4-c7ad-11f1-932b-93e0acbeb039';

    private function filter(array $query = ['from' => '2026-10-10', 'to' => '2026-10-15']): StatsFilter
    {
        return StatsFilter::fromRequest($query, new \DateTimeImmutable('2026-10-15'));
    }

    private function notifications(): array
    {
        return [
            self::A => ['name' => 'Spring sale', 'type' => 'banner'],
            self::B => ['name' => 'Newsletter', 'type' => 'popup'],
            self::C => ['name' => 'Quiet one', 'type' => 'banner'],
        ];
    }

    private function row(string $id, string $date, int $i, int $c = 0, int $d = 0): array
    {
        return ['notificationId' => $id, 'statDate' => $date, 'impressions' => $i, 'clicks' => $c, 'dismissals' => $d];
    }

    public function testTotalsAddEveryNotification(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [
            $this->row(self::A, '2026-10-14', 100, 5, 10),
            $this->row(self::B, '2026-10-15', 40, 2, 8),
        ]);

        $this->assertSame(140, $report->totals->impressions);
        $this->assertSame(7, $report->totals->clicks);
        $this->assertSame(18, $report->totals->dismissals);
    }

    public function testEachNotificationGetsItsOwnTotals(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [
            $this->row(self::A, '2026-10-14', 100, 5, 10),
            $this->row(self::A, '2026-10-15', 50, 1, 0),
            $this->row(self::B, '2026-10-15', 40, 2, 8),
        ]);

        $byId = array_column($report->notifications, null, 'id');

        $this->assertSame(['impressions' => 150, 'clicks' => 6, 'dismissals' => 10], array_intersect_key($byId[self::A], array_flip(['impressions', 'clicks', 'dismissals'])));
        $this->assertSame(40, $byId[self::B]['impressions']);
    }

    public function testNotificationsWithNoActivityStillAppearWithZeroes(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [$this->row(self::A, '2026-10-14', 10)]);

        $byId = array_column($report->notifications, null, 'id');

        $this->assertCount(3, $report->notifications);
        $this->assertSame(0, $byId[self::C]['impressions']);
    }

    public function testNotificationsAreOrderedByImpressionsThenClicksThenName(): void
    {
        $notifications = [
            self::A => ['name' => 'Zebra', 'type' => 'banner'],
            self::B => ['name' => 'apple', 'type' => 'banner'],
            self::C => ['name' => 'Mango', 'type' => 'banner'],
        ];

        $report = StatsReport::build($this->filter(), $notifications, [
            $this->row(self::A, '2026-10-14', 10, 1),
            $this->row(self::B, '2026-10-14', 10, 1),
            $this->row(self::C, '2026-10-14', 50, 0),
        ]);

        $this->assertSame([self::C, self::B, self::A], array_column($report->notifications, 'id'));
    }

    public function testRowsForAnUnknownNotificationAreIgnored(): void
    {
        $report = StatsReport::build($this->filter(), [self::A => ['name' => 'Only', 'type' => 'banner']], [
            $this->row(self::A, '2026-10-14', 10),
            $this->row(self::B, '2026-10-14', 999),
        ]);

        $this->assertSame(10, $report->totals->impressions);
        $this->assertCount(1, $report->notifications);
    }

    public function testRowsOutsideTheRangeAreIgnored(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [
            $this->row(self::A, '2026-10-09', 999),
            $this->row(self::A, '2026-10-10', 1),
            $this->row(self::A, '2026-10-15', 2),
            $this->row(self::A, '2026-10-16', 999),
        ]);

        $this->assertSame(3, $report->totals->impressions);
        $byId = array_column($report->notifications, null, 'id');
        $this->assertSame(3, $byId[self::A]['impressions']);
    }

    public function testTheDailyTrendHasEveryDayOfTheRange(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [$this->row(self::A, '2026-10-12', 7)]);

        $this->assertSame(['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15'], array_keys($report->totals->daily));
        $this->assertSame(7, $report->totals->daily['2026-10-12']['impressions']);
        $this->assertSame(0, $report->totals->daily['2026-10-13']['impressions']);
    }

    public function testDailyRowsHoldOnlyDaysWithActivityOldestFirst(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [
            $this->row(self::B, '2026-10-15', 40, 2, 8),
            $this->row(self::A, '2026-10-12', 100, 5, 10),
            $this->row(self::A, '2026-10-14', 0, 0, 0),
        ]);

        $this->assertSame([
            ['date' => '2026-10-12', 'id' => self::A, 'name' => 'Spring sale', 'type' => 'banner', 'impressions' => 100, 'clicks' => 5, 'dismissals' => 10],
            ['date' => '2026-10-15', 'id' => self::B, 'name' => 'Newsletter', 'type' => 'popup', 'impressions' => 40, 'clicks' => 2, 'dismissals' => 8],
        ], $report->dailyRows);
    }

    public function testSeveralLanguagesOnTheSameDayAreAddedTogether(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [
            $this->row(self::A, '2026-10-12', 10, 1, 0),
            $this->row(self::A, '2026-10-12', 5, 0, 2),
        ]);

        $this->assertCount(1, $report->dailyRows);
        $this->assertSame(15, $report->dailyRows[0]['impressions']);
        $this->assertSame(1, $report->dailyRows[0]['clicks']);
        $this->assertSame(2, $report->dailyRows[0]['dismissals']);
    }

    public function testRatesAreCarriedOnEachNotification(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [$this->row(self::A, '2026-10-12', 200, 5, 50)]);
        $a = array_column($report->notifications, null, 'id')[self::A];

        $this->assertEqualsWithDelta(0.025, $a['clickRate'], 0.00001);
        $this->assertEqualsWithDelta(0.25, $a['dismissalRate'], 0.00001);
    }

    public function testRatesAreNullWithoutImpressions(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), []);
        $c = array_column($report->notifications, null, 'id')[self::C];

        $this->assertNull($c['clickRate']);
        $this->assertNull($c['dismissalRate']);
    }

    public function testAnEmptyReport(): void
    {
        $report = StatsReport::build($this->filter(), [], []);

        $this->assertSame([], $report->notifications);
        $this->assertSame([], $report->dailyRows);
        $this->assertSame(0, $report->totals->impressions);
        $this->assertFalse($report->hasActivity());
    }

    public function testHasActivity(): void
    {
        $this->assertTrue(StatsReport::build($this->filter(), $this->notifications(), [$this->row(self::A, '2026-10-12', 1)])->hasActivity());
        $this->assertTrue(StatsReport::build($this->filter(), $this->notifications(), [$this->row(self::A, '2026-10-12', 0, 1)])->hasActivity());
        $this->assertFalse(StatsReport::build($this->filter(), $this->notifications(), [$this->row(self::A, '2026-10-12', 0)])->hasActivity());
    }

    public function testTheFilterIsKept(): void
    {
        $filter = $this->filter();

        $this->assertSame($filter, StatsReport::build($filter, [], [])->filter);
    }

    public function testJunkCountersAreTreatedAsZero(): void
    {
        $report = StatsReport::build($this->filter(), $this->notifications(), [$this->row(self::A, '2026-10-12', 5) + [], ['notificationId' => self::A, 'statDate' => '2026-10-13', 'impressions' => 'x', 'clicks' => -4, 'dismissals' => null]]);

        $this->assertSame(5, $report->totals->impressions);
        $this->assertSame(0, $report->totals->clicks);
    }

    public function testTheRateHelperMatchesTheSummaryRules(): void
    {
        $this->assertEqualsWithDelta(0.5, StatsSummary::rate(1, 2), 0.00001);
        $this->assertNull(StatsSummary::rate(1, 0));
        $this->assertSame(1.0, StatsSummary::rate(5, 2));
    }
}
