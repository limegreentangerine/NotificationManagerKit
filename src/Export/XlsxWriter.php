<?php

namespace NotificationManagerKit\Export;

use OpenSpout\Writer\AutoFilter;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\{Cell, Row};
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\{Options, Properties, Writer};
use NotificationManagerKit\Support\{StatsExport, StatsReport};
use OpenSpout\Common\Entity\Cell\{EmptyCell, NumericCell, StringCell};

/**
 * Writes a report as an .xlsx workbook: a Summary sheet (one row per notification and a total), a Daily sheet
 * (one row per notification per day with activity) and a Report sheet recording what was asked for.
 *
 * Every text value is written as an explicit string cell. The library's own guess turns anything starting with "="
 * into a live formula, and a notification's name is free text.
 */
final class XlsxWriter
{
    private const RATE_FORMAT = '0.0%';

    private static function notificationLabel(StatsReport $report): string
    {
        if ($report->filter->notificationId === '') {
            return 'All notifications';
        }

        return $report->notifications[0]['name'] ?? $report->filter->notificationId;
    }

    /**
     * @param list<string|int|float|null> $values
     * @param array<int, Style>           $cellStyles by column index, overriding $style
     * @param array<int, Style>           $textStyles
     */
    private static function row(array $values, ?Style $style = null, array $cellStyles = [], array $textStyles = []): Row
    {
        $cells = [];

        foreach (array_values($values) as $i => $value) {
            $cellStyle = $cellStyles[$i] ?? $textStyles[$i] ?? $style;

            $cells[] = match (true) {
                $value === null || $value === '' => new EmptyCell(null, $cellStyle),
                is_int($value) || is_float($value) => new NumericCell($value, $cellStyle),
                default => new StringCell((string) $value, $cellStyle),
            };
        }

        return new Row($cells);
    }

    public static function write(StatsReport $report, string $path, \DateTimeInterface $generated, string $siteName): void
    {
        $writer = new Writer(new Options(properties: new Properties(
            title: 'Notification statistics',
            application: 'Notification Manager',
            creator: 'Notification Manager',
            lastModifiedBy: 'Notification Manager',
        )));
        $writer->openToFile($path);

        $bold = new Style(fontBold: true);
        $rate = new Style(format: self::RATE_FORMAT);
        $boldRate = new Style(fontBold: true, format: self::RATE_FORMAT);

        $summary = StatsExport::summary($report);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Summary');
        $sheet->setColumnWidth(42, 1);
        $sheet->setColumnWidth(10, 2);
        $sheet->setColumnWidth(14, 3, 4, 5, 6, 7);
        $sheet->setSheetView(new SheetView(freezeRow: 2));
        $sheet->setAutoFilter(new AutoFilter(0, 1, count($summary['headers']) - 1, 1 + count($summary['rows'])));

        $writer->addRow(self::row($summary['headers'], $bold));
        foreach ($summary['rows'] as $row) {
            $writer->addRow(self::row($row, null, [4 => $rate, 6 => $rate]));
        }
        $writer->addRow(self::row($summary['total'], $bold, [4 => $boldRate, 6 => $boldRate]));

        $daily = StatsExport::daily($report);
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Daily');
        $sheet->setColumnWidth(12, 1);
        $sheet->setColumnWidth(42, 2);
        $sheet->setColumnWidth(10, 3);
        $sheet->setColumnWidth(14, 4, 5, 6);
        $sheet->setSheetView(new SheetView(freezeRow: 2));

        $writer->addRow(self::row($daily['headers'], $bold));
        foreach ($daily['rows'] as $row) {
            $writer->addRow(self::row($row));
        }

        $filter = $report->filter;
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Report');
        $sheet->setColumnWidth(16, 1);
        $sheet->setColumnWidth(48, 2);

        foreach ([
            ['Site', $siteName],
            ['From', $filter->from->format('Y-m-d')],
            ['To', $filter->to->format('Y-m-d')],
            ['Type', $filter->type === 'all' ? 'All types' : ucfirst($filter->type)],
            ['Notification', self::notificationLabel($report)],
            ['Generated', $generated->format('Y-m-d H:i')],
        ] as $line) {
            $writer->addRow(self::row($line, null, [], [0 => $bold]));
        }

        $writer->close();
    }
}
