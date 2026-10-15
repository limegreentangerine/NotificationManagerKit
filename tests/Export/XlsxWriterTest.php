<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Export;

use PHPUnit\Framework\TestCase;
use OpenSpout\Reader\XLSX\Reader;
use NotificationManagerKit\Export\XlsxWriter;
use NotificationManagerKit\Support\{StatsFilter, StatsReport};

final class XlsxWriterTest extends TestCase
{
    private const A = '441a281e-c719-11f1-bd37-cfbc4319b53f';
    private const B = '60149570-c71b-11f1-bd37-cfbc4319b53f';

    /** @var string[] */
    private array $files = [];

    private function report(?array $notifications = null, ?array $rows = null): StatsReport
    {
        return StatsReport::build(
            StatsFilter::fromRequest(['from' => '2026-10-10', 'to' => '2026-10-15', 'type' => 'banner'], new \DateTimeImmutable('2026-10-15')),
            $notifications ?? [
                self::A => ['name' => 'Spring sale', 'type' => 'banner'],
                self::B => ['name' => 'Café – newsletter', 'type' => 'popup'],
            ],
            $rows ?? [
                ['notificationId' => self::A, 'statDate' => '2026-10-12', 'impressions' => 200, 'clicks' => 5, 'dismissals' => 50],
                ['notificationId' => self::B, 'statDate' => '2026-10-13', 'impressions' => 40, 'clicks' => 2, 'dismissals' => 8],
            ],
        );
    }

    /**
     * @return array<string, list<list<mixed>>> sheet name => rows of cell values
     */
    private function read(StatsReport $report): array
    {
        $path = tempnam(sys_get_temp_dir(), 'nmx');
        $this->files[] = $path;
        XlsxWriter::write($report, $path, new \DateTimeImmutable('2026-10-15 10:30:00'), 'Example Site');

        $reader = new Reader();
        $reader->open($path);

        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }
        $reader->close();

        return $sheets;
    }

    public function testItWritesARealXlsxFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nmx');
        $this->files[] = $path;
        XlsxWriter::write($this->report(), $path, new \DateTimeImmutable('2026-10-15'), 'Site');

        $this->assertSame('PK', file_get_contents($path, false, null, 0, 2), 'an xlsx file is a zip archive');
        $this->assertGreaterThan(1000, filesize($path));
    }

    public function testThreeSheetsInOrder(): void
    {
        $this->assertSame(['Summary', 'Daily', 'Report'], array_keys($this->read($this->report())));
    }

    public function testTheSummarySheetHasHeadersRowsAndATotal(): void
    {
        $rows = $this->read($this->report())['Summary'];

        $this->assertSame(['Notification', 'Type', 'Shown', 'Clicks', 'Click rate', 'Dismissed', 'Dismissal rate'], $rows[0]);
        $this->assertSame(['Spring sale', 'Banner', 200, 5], array_slice($rows[1], 0, 4));
        $this->assertSame(['Café – newsletter', 'Popup', 40, 2], array_slice($rows[2], 0, 4));
        $this->assertSame(['Total', '', 240, 7], array_slice($rows[3], 0, 4));
        $this->assertCount(4, $rows);
    }

    public function testRatesAreRealNumbersForSpreadsheetMaths(): void
    {
        $rows = $this->read($this->report())['Summary'];

        $this->assertEqualsWithDelta(0.025, $rows[1][4], 0.00001);
        $this->assertEqualsWithDelta(0.25, $rows[1][6], 0.00001);
        $this->assertEqualsWithDelta(7 / 240, $rows[3][4], 0.00001);
    }

    public function testARateWithNothingShownIsBlankNotZero(): void
    {
        $rows = $this->read($this->report(rows: []))['Summary'];

        // Blank, so a spreadsheet doesn't average in a misleading 0%.
        $this->assertSame('', $rows[1][4]);
        $this->assertSame('', $rows[1][6]);
    }

    public function testTheDailySheetListsDaysWithActivity(): void
    {
        $rows = $this->read($this->report())['Daily'];

        $this->assertSame(['Date', 'Notification', 'Type', 'Shown', 'Clicks', 'Dismissed'], $rows[0]);
        $this->assertSame(['2026-10-12', 'Spring sale', 'Banner', 200, 5, 50], $rows[1]);
        $this->assertSame(['2026-10-13', 'Café – newsletter', 'Popup', 40, 2, 8], $rows[2]);
        $this->assertCount(3, $rows);
    }

    public function testTheReportSheetRecordsWhatWasAskedFor(): void
    {
        $rows = $this->read($this->report())['Report'];
        $flat = array_column($rows, 1, 0);

        $this->assertSame('Example Site', $flat['Site']);
        $this->assertSame('2026-10-10', $flat['From']);
        $this->assertSame('2026-10-15', $flat['To']);
        $this->assertSame('Banner', $flat['Type']);
        $this->assertSame('All notifications', $flat['Notification']);
        $this->assertSame('2026-10-15 10:30', $flat['Generated']);
    }

    public function testTheReportSheetNamesASingleNotification(): void
    {
        $report = StatsReport::build(
            StatsFilter::fromRequest(['range' => '7', 'notification' => self::A], new \DateTimeImmutable('2026-10-15')),
            [self::A => ['name' => 'Spring sale', 'type' => 'banner']],
            [],
        );

        $this->assertSame('Spring sale', array_column($this->read($report)['Report'], 1, 0)['Notification']);
    }

    /**
     * A cell that starts with "=" would be written as a live formula by the library's default, and "+", "-" and "@"
     * are treated as formulas by some spreadsheet programs. A notification's name is typed by an administrator, but
     * it must still only ever be text.
     */
    public function testNamesThatLookLikeFormulasStayText(): void
    {
        $names = ['=SUM(A1:A9)', '+1+1', '-2+3', '@SUM(1)', '=HYPERLINK("http://evil.example","x")'];
        $notifications = [];
        foreach ($names as $i => $name) {
            $notifications[sprintf('00000000-0000-0000-0000-%012d', $i)] = ['name' => $name, 'type' => 'banner'];
        }

        $sheets = $this->read($this->report($notifications, []));
        $written = array_column(array_slice($sheets['Summary'], 1, count($names)), 0);

        sort($written);
        $expected = $names;
        sort($expected);
        $this->assertSame($expected, $written);
    }

    public function testFormulaLookingNamesAreStoredAsStringsNotFormulaNodes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nmx');
        $this->files[] = $path;
        $report = $this->report(['00000000-0000-0000-0000-000000000001' => ['name' => '=1+1', 'type' => 'banner']], []);
        XlsxWriter::write($report, $path, new \DateTimeImmutable('2026-10-15'), 'Site');

        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringNotContainsString('<f>', $xml, 'no cell may hold a formula');
    }

    public function testASpecialCharacterNameSurvives(): void
    {
        $rows = $this->read($this->report(['00000000-0000-0000-0000-000000000001' => ['name' => '<b>"Tom & Jerry" 日本語 😀</b>', 'type' => 'popup']], []))['Summary'];

        $this->assertSame('<b>"Tom & Jerry" 日本語 😀</b>', $rows[1][0]);
    }

    public function testAnEmptyReportStillMakesAValidWorkbook(): void
    {
        $sheets = $this->read($this->report([], []));

        $this->assertSame(['Notification', 'Type', 'Shown', 'Clicks', 'Click rate', 'Dismissed', 'Dismissal rate'], $sheets['Summary'][0]);
        $this->assertSame('Total', $sheets['Summary'][1][0]);
        $this->assertCount(1, $sheets['Daily']);
    }

    public function testTheSummaryHeaderIsFrozenAndFilterable(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nmx');
        $this->files[] = $path;
        XlsxWriter::write($this->report(), $path, new \DateTimeImmutable('2026-10-15'), 'Site');

        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('<pane', $xml);
        $this->assertStringContainsString('<autoFilter', $xml);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }
}
