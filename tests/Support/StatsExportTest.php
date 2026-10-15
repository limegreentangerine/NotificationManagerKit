<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Support\{StatsExport, StatsFilter, StatsReport};

final class StatsExportTest extends TestCase
{
    private const A = '441a281e-c719-11f1-bd37-cfbc4319b53f';
    private const B = '60149570-c71b-11f1-bd37-cfbc4319b53f';

    private function report(array $query = ['from' => '2026-10-10', 'to' => '2026-10-15'], ?array $notifications = null, ?array $rows = null): StatsReport
    {
        return StatsReport::build(
            StatsFilter::fromRequest($query, new \DateTimeImmutable('2026-10-15')),
            $notifications ?? [
                self::A => ['name' => 'Spring "sale" & more', 'type' => 'banner'],
                self::B => ['name' => 'Café – newsletter', 'type' => 'popup'],
            ],
            $rows ?? [
                ['notificationId' => self::A, 'statDate' => '2026-10-12', 'impressions' => 200, 'clicks' => 5, 'dismissals' => 50],
                ['notificationId' => self::B, 'statDate' => '2026-10-13', 'impressions' => 40, 'clicks' => 2, 'dismissals' => 8],
            ],
        );
    }

    private function generated(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-15 10:30:00', new \DateTimeZone('Europe/London'));
    }

    public function testJsonDescribesTheRangeFiltersAndTotals(): void
    {
        $data = json_decode(StatsExport::json($this->report(), $this->generated()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('2026-10-15T10:30:00+01:00', $data['generated']);
        $this->assertSame(['from' => '2026-10-10', 'to' => '2026-10-15', 'days' => 6], $data['range']);
        $this->assertSame(['type' => 'all', 'notification' => null], $data['filters']);
        $this->assertSame(240, $data['totals']['impressions']);
        $this->assertSame(7, $data['totals']['clicks']);
        $this->assertSame(58, $data['totals']['dismissals']);
        $this->assertEqualsWithDelta(7 / 240, $data['totals']['clickRate'], 0.0001);
    }

    public function testJsonListsNotificationsBusiestFirstWithRates(): void
    {
        $data = json_decode(StatsExport::json($this->report(), $this->generated()), true);

        $this->assertSame([self::A, self::B], array_column($data['notifications'], 'id'));
        $this->assertSame([
            'id' => self::A,
            'name' => 'Spring "sale" & more',
            'type' => 'banner',
            'impressions' => 200,
            'clicks' => 5,
            'clickRate' => 0.025,
            'dismissals' => 50,
            'dismissalRate' => 0.25,
        ], $data['notifications'][0]);
    }

    public function testJsonDailyRowsAreTheDaysWithActivity(): void
    {
        $data = json_decode(StatsExport::json($this->report(), $this->generated()), true);

        $this->assertSame(
            [
                ['date' => '2026-10-12', 'id' => self::A, 'name' => 'Spring "sale" & more', 'type' => 'banner', 'impressions' => 200, 'clicks' => 5, 'dismissals' => 50],
                ['date' => '2026-10-13', 'id' => self::B, 'name' => 'Café – newsletter', 'type' => 'popup', 'impressions' => 40, 'clicks' => 2, 'dismissals' => 8],
            ],
            $data['daily'],
        );
    }

    public function testJsonKeepsUnicodeReadableAndRatesNullWithoutImpressions(): void
    {
        $json = StatsExport::json($this->report(rows: []), $this->generated());

        $this->assertStringContainsString('Café – newsletter', $json);
        $data = json_decode($json, true);
        $this->assertNull($data['totals']['clickRate']);
        $this->assertNull($data['notifications'][0]['clickRate']);
        $this->assertSame([], $data['daily']);
    }

    public function testJsonRecordsTheFiltersInUse(): void
    {
        $report = $this->report(['from' => '2026-10-10', 'to' => '2026-10-15', 'type' => 'popup', 'notification' => self::B]);
        $data = json_decode(StatsExport::json($report, $this->generated()), true);

        $this->assertSame(['type' => 'popup', 'notification' => self::B], $data['filters']);
    }

    public function testJsonSurvivesBadUtf8InANameWithoutThrowing(): void
    {
        $report = $this->report(notifications: [self::A => ['name' => "Bad \xB1 byte", 'type' => 'banner']]);

        $this->assertJson(StatsExport::json($report, $this->generated()));
    }

    public function testSummaryTableHasHeadersRowsAndATotal(): void
    {
        $table = StatsExport::summary($this->report());

        $this->assertSame(['Notification', 'Type', 'Shown', 'Clicks', 'Click rate', 'Dismissed', 'Dismissal rate'], $table['headers']);
        $this->assertSame(['Spring "sale" & more', 'Banner', 200, 5, 0.025, 50, 0.25], $table['rows'][0]);
        $this->assertSame(['Café – newsletter', 'Popup', 40, 2, 0.05, 8, 0.2], $table['rows'][1]);
        $this->assertSame(['Total', '', 240, 7, 7 / 240, 58, 58 / 240], $table['total']);
    }

    public function testSummaryRatesAreNullWhenNothingWasShown(): void
    {
        $table = StatsExport::summary($this->report(rows: []));

        $this->assertNull($table['rows'][0][4]);
        $this->assertNull($table['rows'][0][6]);
        $this->assertNull($table['total'][4]);
    }

    public function testDailyTableHasHeadersAndOneRowPerDayWithActivity(): void
    {
        $table = StatsExport::daily($this->report());

        $this->assertSame(['Date', 'Notification', 'Type', 'Shown', 'Clicks', 'Dismissed'], $table['headers']);
        $this->assertSame([
            ['2026-10-12', 'Spring "sale" & more', 'Banner', 200, 5, 50],
            ['2026-10-13', 'Café – newsletter', 'Popup', 40, 2, 8],
        ], $table['rows']);
    }

    public function testFilenamesCarryTheRange(): void
    {
        $this->assertSame('notification-statistics-2026-10-10_to_2026-10-15.xlsx', StatsExport::filename($this->report(), 'xlsx'));
        $this->assertSame('notification-statistics-2026-10-10_to_2026-10-15.pdf', StatsExport::filename($this->report(), 'pdf'));
    }

    public function testASingleDayFilenameIsStillValid(): void
    {
        $name = StatsExport::filename($this->report(['from' => '2026-10-15', 'to' => '2026-10-15']), 'json');

        $this->assertSame('notification-statistics-2026-10-15_to_2026-10-15.json', $name);
    }

    public function testOnlyKnownFormatsAreAllowed(): void
    {
        $this->assertTrue(StatsExport::isFormat('xlsx'));
        $this->assertTrue(StatsExport::isFormat('json'));
        $this->assertTrue(StatsExport::isFormat('pdf'));
        $this->assertFalse(StatsExport::isFormat('csv'));
        $this->assertFalse(StatsExport::isFormat('../etc/passwd'));
        $this->assertFalse(StatsExport::isFormat(''));
    }
}
