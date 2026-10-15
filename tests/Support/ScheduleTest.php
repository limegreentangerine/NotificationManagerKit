<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Support\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;

final class ScheduleTest extends TestCase
{
    private function at(string $when): \DateTimeImmutable
    {
        return new \DateTimeImmutable($when);
    }

    public function testNoWindowIsAlwaysActive(): void
    {
        $this->assertTrue((new Schedule())->isActiveAt($this->at('2030-01-01 12:00')));
    }

    public function testWindowEdges(): void
    {
        $schedule = new Schedule($this->at('2026-03-01 09:00'), $this->at('2026-03-31 17:00'));

        $this->assertFalse($schedule->isActiveAt($this->at('2026-03-01 08:59:59')));
        $this->assertTrue($schedule->isActiveAt($this->at('2026-03-01 09:00')));
        $this->assertTrue($schedule->isActiveAt($this->at('2026-03-31 17:00')));
        $this->assertFalse($schedule->isActiveAt($this->at('2026-03-31 17:00:01')));
    }

    public function testOpenEndedWindows(): void
    {
        $this->assertTrue((new Schedule($this->at('2026-01-01')))->isActiveAt($this->at('2099-01-01')));
        $this->assertFalse((new Schedule($this->at('2026-01-01')))->isActiveAt($this->at('2025-12-31')));
        $this->assertTrue((new Schedule(null, $this->at('2026-01-01')))->isActiveAt($this->at('2000-01-01')));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function recurrenceProvider(): array
    {
        return [
            'weekdays on a Friday' => ['weekdays', '2026-03-06', true],
            'weekdays on a Saturday' => ['weekdays', '2026-03-07', false],
            'monday on a Monday' => ['weekly_monday', '2026-03-09', true],
            'monday on a Tuesday' => ['weekly_monday', '2026-03-10', false],
            'fourth monday (23rd)' => ['monthly_fourth_monday', '2026-03-23', true],
            'third monday (16th)' => ['monthly_fourth_monday', '2026-03-16', false],
            'fifth monday (30th)' => ['monthly_fourth_monday', '2026-03-30', false],
            'annually on anniversary' => ['annually', '2026-03-01', true],
            'annually off anniversary' => ['annually', '2026-03-02', false],
            'daily' => ['daily', '2026-03-07', true],
            'unknown rule fails open' => ['bogus', '2026-03-07', true],
        ];
    }

    #[DataProvider('recurrenceProvider')]
    public function testRecurrence(string $rule, string $day, bool $expected): void
    {
        $schedule = new Schedule($this->at('2025-03-01'), null, $rule);

        $this->assertSame($expected, $schedule->isActiveAt($this->at($day . ' 10:00')));
    }

    public function testRecurrenceIsIgnoredWithoutAStartDate(): void
    {
        $this->assertTrue((new Schedule(null, null, 'weekly_monday'))->isActiveAt($this->at('2026-03-10')));
    }

    public function testNextChangeIsTheNearestFutureBoundary(): void
    {
        $now = $this->at('2026-03-05 10:00');
        $schedule = new Schedule($this->at('2026-03-01'), $this->at('2026-03-31'));

        $this->assertEquals($this->at('2026-03-31'), $schedule->nextChangeAfter($now));
        $this->assertEquals($this->at('2026-03-10'), (new Schedule($this->at('2026-03-10')))->nextChangeAfter($now));
        $this->assertNull((new Schedule())->nextChangeAfter($now));
        $this->assertNull((new Schedule($this->at('2026-03-01')))->nextChangeAfter($now));
    }

    public function testRecurringSchedulesChangeAtMidnight(): void
    {
        $now = $this->at('2026-03-05 10:00');
        $schedule = new Schedule($this->at('2026-03-01'), $this->at('2026-03-31'), 'weekdays');

        $this->assertEquals($this->at('2026-03-06 00:00'), $schedule->nextChangeAfter($now));
    }

    public function testRepeatEventValidation(): void
    {
        $this->assertTrue(Schedule::isValidRepeatEvent('daily'));
        $this->assertFalse(Schedule::isValidRepeatEvent('hourly'));
    }
}
