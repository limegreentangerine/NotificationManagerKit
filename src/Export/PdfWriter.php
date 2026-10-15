<?php

namespace NotificationManagerKit\Export;

use Dompdf\{Dompdf, Options};
use NotificationManagerKit\Support\StatsReport;

/**
 * Renders a report as a PDF: the totals and one table row per notification, on landscape A4. The daily breakdown
 * is left to the Excel and JSON exports, where it is useful; in a PDF it would run to pages.
 *
 * The template lives in elements/, not here: Concrete scans everything under src/ for entity classes and would
 * execute a template file as it went.
 *
 * dompdf is locked down: no remote files, no PHP, no JavaScript. Its bundled DejaVu Sans covers accented and
 * non-Latin names, and its writable cache goes to the temp directory so a read-only vendor/ folder is fine.
 */
final class PdfWriter
{
    /**
     * @param callable|null $translate fn(string $text, mixed ...$args): string; defaults to the English text as is
     */
    public static function html(StatsReport $report, \DateTimeInterface $generated, string $siteName, ?callable $translate = null): string
    {
        $t = $translate ?? fn(string $text, mixed ...$args): string => $args === [] ? $text : vsprintf($text, $args);

        ob_start();

        try {
            (static function () use ($report, $generated, $siteName, $t): void {
                require dirname(__DIR__, 2) . '/elements/export/statistics_pdf.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    /**
     * @param callable|null $translate see html()
     *
     * @return string the PDF file's contents
     */
    public static function render(StatsReport $report, \DateTimeInterface $generated, string $siteName, ?callable $translate = null): string
    {
        $cache = sys_get_temp_dir() . '/notification_manager_kit_dompdf';

        if (!is_dir($cache)) {
            @mkdir($cache, 0700, true);
        }

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setFontCache($cache);
        $options->setTempDir($cache);
        $options->setChroot([__DIR__]);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml(self::html($report, $generated, $siteName, $translate), 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
