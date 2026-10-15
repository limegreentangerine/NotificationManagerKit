<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use NotificationManagerKit\Support\StatsFilter;

final class StatsFilterTest extends TestCase
{
    private const ID = '441a281e-c719-11f1-bd37-cfbc4319b53f';

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-15 15:45:00');
    }

    private function filter(array $query = []): StatsFilter
    {
        return StatsFilter::fromRequest($query, $this->today());
    }

    public function testDefaultsToTheLastThirtyDays(): void
    {
        $filter = $this->filter();

        $this->assertSame('2026-09-16', $filter->from->format('Y-m-d'));
        $this->assertSame('2026-10-15', $filter->to->format('Y-m-d'));
        $this->assertSame(30, $filter->days());
        $this->assertSame(30, $filter->preset);
        $this->assertSame('all', $filter->type);
        $this->assertSame('', $filter->notificationId);
    }

    #[DataProvider('presets')]
    public function testPresets(string $range, int $days): void
    {
        $filter = $this->filter(['range' => $range]);

        $this->assertSame($days, $filter->days());
        $this->assertSame($days, $filter->preset);
        $this->assertSame('2026-10-15', $filter->to->format('Y-m-d'));
    }

    public static function presets(): array
    {
        return [['7', 7], ['30', 30], ['90', 90]];
    }

    public function testUnknownPresetsFallBackToTheDefault(): void
    {
        $this->assertSame(30, $this->filter(['range' => '45'])->days());
        $this->assertSame(30, $this->filter(['range' => 'abc'])->days());
        $this->assertSame(30, $this->filter(['range' => ['7']])->days());
    }

    public function testACustomRange(): void
    {
        $filter = $this->filter(['from' => '2026-10-01', 'to' => '2026-10-10']);

        $this->assertSame('2026-10-01', $filter->from->format('Y-m-d'));
        $this->assertSame('2026-10-10', $filter->to->format('Y-m-d'));
        $this->assertSame(10, $filter->days());
        $this->assertNull($filter->preset);
    }

    public function testASingleDayRange(): void
    {
        $this->assertSame(1, $this->filter(['from' => '2026-10-10', 'to' => '2026-10-10'])->days());
    }

    public function testAPresetWinsOverCustomDates(): void
    {
        $filter = $this->filter(['range' => '7', 'from' => '2026-01-01', 'to' => '2026-01-31']);

        $this->assertSame(7, $filter->days());
    }

    public function testTheEndCannotBeInTheFuture(): void
    {
        $filter = $this->filter(['from' => '2026-10-10', 'to' => '2027-01-01']);

        $this->assertSame('2026-10-15', $filter->to->format('Y-m-d'));
    }

    public function testBackwardsDatesAreSwapped(): void
    {
        $filter = $this->filter(['from' => '2026-10-10', 'to' => '2026-10-01']);

        $this->assertSame('2026-10-01', $filter->from->format('Y-m-d'));
        $this->assertSame('2026-10-10', $filter->to->format('Y-m-d'));
    }

    public function testAFromWithoutAToRunsToToday(): void
    {
        $filter = $this->filter(['from' => '2026-10-10']);

        $this->assertSame('2026-10-10', $filter->from->format('Y-m-d'));
        $this->assertSame('2026-10-15', $filter->to->format('Y-m-d'));
    }

    public function testAToWithoutAFromLooksBackThirtyDays(): void
    {
        $filter = $this->filter(['to' => '2026-10-10']);

        $this->assertSame('2026-10-10', $filter->to->format('Y-m-d'));
        $this->assertSame(30, $filter->days());
    }

    public function testTheRangeIsCappedAtTwoYears(): void
    {
        $filter = $this->filter(['from' => '2000-01-01', 'to' => '2026-10-15']);

        $this->assertSame(StatsFilter::MAX_DAYS, $filter->days());
        $this->assertSame('2026-10-15', $filter->to->format('Y-m-d'));
    }

    #[DataProvider('badDates')]
    public function testInvalidDatesAreIgnored(array $query): void
    {
        $filter = $this->filter($query);

        $this->assertSame(30, $filter->days());
        $this->assertSame('2026-10-15', $filter->to->format('Y-m-d'));
    }

    public static function badDates(): array
    {
        return [
            'text' => [['from' => 'yesterday', 'to' => 'today']],
            'impossible day' => [['from' => '2026-02-31', 'to' => '2026-04-31']],
            'wrong format' => [['from' => '15/10/2026', 'to' => '16/10/2026']],
            'with time' => [['from' => '2026-10-01 10:00', 'to' => '2026-10-10 10:00']],
            'array' => [['from' => ['2026-10-01'], 'to' => ['2026-10-10']]],
            'sql' => [['from' => "2026-10-01' OR 1=1", 'to' => "2026-10-10'; DROP TABLE x"]],
            'empty' => [['from' => '', 'to' => '']],
        ];
    }

    public function testTheTypeIsAllowListed(): void
    {
        $this->assertSame('banner', $this->filter(['type' => 'banner'])->type);
        $this->assertSame('popup', $this->filter(['type' => 'popup'])->type);
        $this->assertSame('all', $this->filter(['type' => 'all'])->type);
        $this->assertSame('all', $this->filter(['type' => 'toast'])->type);
        $this->assertSame('all', $this->filter(['type' => ['banner']])->type);
    }

    public function testTheNotificationMustLookLikeAnId(): void
    {
        $this->assertSame(self::ID, $this->filter(['notification' => self::ID])->notificationId);
        $this->assertSame(self::ID, $this->filter(['notification' => strtoupper(self::ID)])->notificationId);
        $this->assertSame('', $this->filter(['notification' => "x' OR 1=1"])->notificationId);
        $this->assertSame('', $this->filter(['notification' => ['a']])->notificationId);
    }

    public function testQueryRebuildsAPresetFilter(): void
    {
        $this->assertSame(['range' => 7], $this->filter(['range' => '7'])->toQuery());
        $this->assertSame(['range' => 30], $this->filter()->toQuery());
    }

    public function testQueryRebuildsACustomFilterWithItsOtherSelections(): void
    {
        $query = $this->filter(['from' => '2026-10-01', 'to' => '2026-10-10', 'type' => 'popup', 'notification' => self::ID])->toQuery();

        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-10', 'type' => 'popup', 'notification' => self::ID], $query);
    }

    public function testQueryOmitsTheDefaults(): void
    {
        $this->assertArrayNotHasKey('type', $this->filter(['type' => 'all'])->toQuery());
        $this->assertArrayNotHasKey('notification', $this->filter()->toQuery());
    }

    public function testAFilterRebuiltFromItsOwnQueryIsTheSame(): void
    {
        $original = $this->filter(['from' => '2026-09-01', 'to' => '2026-09-20', 'type' => 'banner', 'notification' => self::ID]);

        $this->assertEquals($original, $this->filter($original->toQuery()));
    }

    public function testOnlyTheBadDateIsIgnoredWhenTheOtherIsFine(): void
    {
        $filter = $this->filter(['from' => 'nonsense', 'to' => '2026-10-10']);

        $this->assertSame('2026-10-10', $filter->to->format('Y-m-d'));
        $this->assertSame(30, $filter->days());
    }

    public function testDatesAreMidnight(): void
    {
        $this->assertSame('00:00:00', $this->filter()->to->format('H:i:s'));
        $this->assertSame('00:00:00', $this->filter()->from->format('H:i:s'));
    }
}
