<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Entity;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Entity\Settings;
use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsStatsTest extends TestCase
{
    public function testStatisticsAreOnByDefaultWithNinetyDaysRetention(): void
    {
        $settings = new Settings();

        $this->assertTrue($settings->areStatsEnabled());
        $this->assertSame(90, $settings->getStatsRetentionDays());
    }

    public function testCanBeTurnedOff(): void
    {
        $this->assertFalse((new Settings())->setStatsEnabled(false)->areStatsEnabled());
        $this->assertFalse((new Settings())->setStatsEnabled(null)->areStatsEnabled());
    }

    #[DataProvider('clamped')]
    public function testRetentionIsClampedIntoTheAllowedRange(mixed $given, int $expected): void
    {
        $this->assertSame($expected, (new Settings())->setStatsRetentionDays($given)->getStatsRetentionDays());
    }

    public static function clamped(): array
    {
        return [
            'normal' => [30, 30],
            'minimum' => [7, 7],
            'maximum' => [730, 730],
            'too small' => [1, 7],
            'negative' => [-50, 7],
            'too large' => [100000, 730],
            'numeric string' => ['45', 45],
            'junk becomes the minimum' => ['abc', 7],
        ];
    }

    #[DataProvider('validity')]
    public function testRetentionValidation(mixed $days, bool $valid): void
    {
        $this->assertSame($valid, Settings::isValidRetentionDays($days));
    }

    public static function validity(): array
    {
        return [
            'int' => [90, true],
            'string' => ['90', true],
            'padded' => [' 90 ', true],
            'minimum' => [7, true],
            'maximum' => [730, true],
            'below' => [6, false],
            'above' => [731, false],
            'blank' => ['', false],
            'decimal' => ['10.5', false],
            'negative' => ['-30', false],
            'text' => ['soon', false],
            'null' => [null, false],
            'array' => [[30], false],
        ];
    }
}
