<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Export;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Export\PdfWriter;
use NotificationManagerKit\Support\{StatsFilter, StatsReport};

final class PdfWriterTest extends TestCase
{
    private const A = '441a281e-c719-11f1-bd37-cfbc4319b53f';
    private const B = '60149570-c71b-11f1-bd37-cfbc4319b53f';

    private function report(?array $notifications = null, ?array $rows = null, array $query = ['from' => '2026-10-10', 'to' => '2026-10-15']): StatsReport
    {
        return StatsReport::build(
            StatsFilter::fromRequest($query, new \DateTimeImmutable('2026-10-15')),
            $notifications ?? [
                self::A => ['name' => 'Spring sale', 'type' => 'banner'],
                self::B => ['name' => 'Café – newsletter', 'type' => 'popup'],
            ],
            $rows ?? [
                ['notificationId' => self::A, 'statDate' => '2026-10-12', 'impressions' => 1200, 'clicks' => 30, 'dismissals' => 50],
                ['notificationId' => self::B, 'statDate' => '2026-10-13', 'impressions' => 40, 'clicks' => 2, 'dismissals' => 8],
            ],
        );
    }

    private function generated(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-15 10:30:00');
    }

    private function html(?StatsReport $report = null, ?callable $translate = null): string
    {
        return PdfWriter::html($report ?? $this->report(), $this->generated(), 'Example Site', $translate);
    }

    public function testRendersARealPdf(): void
    {
        $pdf = PdfWriter::render($this->report(), $this->generated(), 'Example Site');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf));
    }

    public function testRendersAnEmptyReport(): void
    {
        $pdf = PdfWriter::render($this->report([], []), $this->generated(), 'Example Site');

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function testRendersUnicodeAndHostileNamesWithoutFailing(): void
    {
        $notifications = [
            self::A => ['name' => '<script>alert(1)</script> "Tom & Jerry" 日本語 😀', 'type' => 'banner'],
            self::B => ['name' => '<img src="http://169.254.169.254/latest/meta-data/">', 'type' => 'popup'],
        ];

        $pdf = PdfWriter::render($this->report($notifications, []), $this->generated(), 'Site <b>x</b>');

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function testHtmlShowsTheRangeTotalsAndEveryNotification(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('2026-10-10 to 2026-10-15 (6 days)', $html);
        $this->assertStringContainsString('Example Site', $html);
        $this->assertStringContainsString('Generated 2026-10-15 10:30', $html);
        $this->assertStringContainsString('Spring sale', $html);
        $this->assertStringContainsString('Café – newsletter', $html);
        $this->assertStringContainsString('1,240', $html, 'total shown');
        $this->assertStringContainsString('2.5%', $html, 'spring sale click rate');
        $this->assertStringContainsString('Banner', $html);
        $this->assertStringContainsString('Popup', $html);
    }

    public function testHtmlEscapesEveryValue(): void
    {
        $notifications = [self::A => ['name' => '<script>alert(1)</script><img src=x onerror=alert(2)>', 'type' => 'banner']];
        $html = PdfWriter::html($this->report($notifications, []), $this->generated(), '<b>Site</b>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>Site</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Site&lt;/b&gt;', $html);
    }

    public function testHtmlDescribesTheFiltersInUse(): void
    {
        $html = $this->html($this->report(
            [self::B => ['name' => 'Newsletter', 'type' => 'popup']],
            [],
            ['from' => '2026-10-10', 'to' => '2026-10-15', 'type' => 'popup', 'notification' => self::B],
        ));

        $this->assertStringContainsString('Type: Popup', $html);
        $this->assertStringContainsString('Notification: Newsletter', $html);
    }

    public function testHtmlSaysWhenThereIsNothingToReport(): void
    {
        $this->assertStringContainsString('There are no notifications to report on', $this->html($this->report([], [])));
    }

    public function testRatesAreADashWhenNothingWasShown(): void
    {
        $html = $this->html($this->report([self::A => ['name' => 'Quiet', 'type' => 'banner']], []));

        $this->assertStringContainsString('–', $html);
    }

    public function testTextGoesThroughTheTranslator(): void
    {
        $html = $this->html(null, fn(string $text, mixed ...$args) => strtoupper($args === [] ? $text : vsprintf($text, $args)));

        $this->assertStringContainsString('NOTIFICATION STATISTICS', $html);
        $this->assertStringContainsString('GENERATED 2026-10-15 10:30', $html);
    }

    public function testNothingLeaksIntoTheOutputBufferWhenTheTemplateFails(): void
    {
        $level = ob_get_level();

        try {
            PdfWriter::html($this->report(), $this->generated(), 'Site', function (): string {
                throw new \RuntimeException('translator exploded');
            });
            $this->fail('expected the exception');
        } catch (\RuntimeException) {
            // Expected.
        }

        $this->assertSame($level, ob_get_level());
    }
}
