<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Support\StatsSummary;

final class StatsSummaryTest extends TestCase
{
    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-03-10 15:30:00');
    }

    public function testNoRowsGivesZeroesForEveryDay(): void
    {
        $summary = StatsSummary::fromRows([], $this->today(), 7);

        $this->assertSame(0, $summary->impressions);
        $this->assertCount(7, $summary->daily);
        $this->assertSame(['2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08', '2026-03-09', '2026-03-10'], array_keys($summary->daily));
        $this->assertSame(['impressions' => 0, 'clicks' => 0, 'dismissals' => 0], $summary->daily['2026-03-10']);
    }

    public function testRowsAreTotalledAndPlacedOnTheirDay(): void
    {
        $summary = StatsSummary::fromRows([
            ['statDate' => '2026-03-10', 'impressions' => 100, 'clicks' => 5, 'dismissals' => 20],
            ['statDate' => '2026-03-09', 'impressions' => 50, 'clicks' => 1, 'dismissals' => 0],
        ], $this->today(), 7);

        $this->assertSame(150, $summary->impressions);
        $this->assertSame(6, $summary->clicks);
        $this->assertSame(20, $summary->dismissals);
        $this->assertSame(100, $summary->daily['2026-03-10']['impressions']);
        $this->assertSame(50, $summary->daily['2026-03-09']['impressions']);
    }

    public function testSeveralLanguagesOnTheSameDayAreAdded(): void
    {
        $summary = StatsSummary::fromRows([
            ['statDate' => '2026-03-10', 'impressions' => 10, 'clicks' => 1, 'dismissals' => 0],
            ['statDate' => '2026-03-10', 'impressions' => 5, 'clicks' => 2, 'dismissals' => 1],
        ], $this->today(), 3);

        $this->assertSame(['impressions' => 15, 'clicks' => 3, 'dismissals' => 1], $summary->daily['2026-03-10']);
    }

    public function testRowsOutsideTheWindowAreIgnored(): void
    {
        $summary = StatsSummary::fromRows([
            ['statDate' => '2026-02-01', 'impressions' => 999, 'clicks' => 9, 'dismissals' => 9],
            ['statDate' => '2026-03-11', 'impressions' => 999, 'clicks' => 9, 'dismissals' => 9],
        ], $this->today(), 7);

        $this->assertSame(0, $summary->impressions);
    }

    public function testRatesAreFractionsOfImpressions(): void
    {
        $summary = StatsSummary::fromRows([['statDate' => '2026-03-10', 'impressions' => 200, 'clicks' => 5, 'dismissals' => 50]], $this->today(), 1);

        $this->assertEqualsWithDelta(0.025, $summary->clickRate(), 0.00001);
        $this->assertEqualsWithDelta(0.25, $summary->dismissalRate(), 0.00001);
    }

    public function testRatesAreNullWithoutImpressions(): void
    {
        $summary = StatsSummary::fromRows([], $this->today(), 1);

        $this->assertNull($summary->clickRate());
        $this->assertNull($summary->dismissalRate());
    }

    public function testRateIsCappedAtOne(): void
    {
        // Counters from different page views can briefly disagree (a click recorded without its impression).
        $summary = StatsSummary::fromRows([['statDate' => '2026-03-10', 'impressions' => 1, 'clicks' => 3, 'dismissals' => 0]], $this->today(), 1);

        $this->assertSame(1.0, $summary->clickRate());
    }

    public function testPercentFormatting(): void
    {
        $this->assertSame('2.5%', StatsSummary::percent(0.025));
        $this->assertSame('100%', StatsSummary::percent(1.0));
        $this->assertSame('0%', StatsSummary::percent(0.0));
        $this->assertSame('12.3%', StatsSummary::percent(0.1234));
        $this->assertSame('–', StatsSummary::percent(null));
    }

    public function testNegativeOrJunkCountersAreTreatedAsZero(): void
    {
        $summary = StatsSummary::fromRows([['statDate' => '2026-03-10', 'impressions' => -5, 'clicks' => 'x', 'dismissals' => null]], $this->today(), 1);

        $this->assertSame(0, $summary->impressions);
        $this->assertSame(0, $summary->clicks);
        $this->assertSame(0, $summary->dismissals);
    }

    public function testWindowIsAtLeastOneDay(): void
    {
        $this->assertCount(1, StatsSummary::fromRows([], $this->today(), 0)->daily);
    }
}
