<?php

/**
 * HTML for the PDF export. Rendered by Export\PdfWriter, never exposed to a browser. Every value goes through
 * Output::e(): names are free text typed by administrators.
 *
 * @var NotificationManagerKit\Support\StatsReport $report
 * @var \DateTimeInterface                         $generated
 * @var string                                     $siteName
 * @var callable                                   $t translator: $t('text %s', $arg)
 */

use NotificationManagerKit\Support\{Output as O, StatsSummary};

// Only ever included by Export\PdfWriter, which supplies these. Requested directly there is nothing to show.
if (!isset($report, $generated, $siteName, $t)) {
    exit;
}

$filter = $report->filter;
$totals = $report->totals;
$typeLabels = ['banner' => $t('Banner'), 'popup' => $t('Popup')];
$typeFilter = $filter->type === 'all' ? $t('All types') : ($typeLabels[$filter->type] ?? $filter->type);
$notificationFilter = $filter->notificationId === '' ? $t('All notifications') : ($report->notifications[0]['name'] ?? $filter->notificationId);
$n = fn(int $value): string => number_format($value);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo O::e($t('Notification statistics')) ?></title>
<style>
    @page { margin: 28px 32px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #1f2933; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .meta { color: #52606d; margin: 0 0 14px; line-height: 1.5; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 5px 7px; text-align: right; border-bottom: 1px solid #d9dee4; }
    th:first-child, td:first-child, th.text, td.text { text-align: left; }
    thead { display: table-header-group; }
    thead th { background: #eef1f4; font-weight: bold; border-bottom: 1px solid #b8c0c9; }
    tfoot td { font-weight: bold; border-top: 1px solid #b8c0c9; border-bottom: none; }
    tr { page-break-inside: avoid; }
    .totals { margin-bottom: 16px; }
    .totals td { text-align: left; border: 1px solid #d9dee4; padding: 8px 10px; width: 20%; }
    .totals .label { color: #52606d; font-size: 8px; }
    .totals .value { font-size: 16px; font-weight: bold; }
    .note { margin-top: 14px; color: #52606d; font-size: 8px; }
</style>
</head>
<body>
    <h1><?php echo O::e($t('Notification statistics')) ?></h1>
    <p class="meta">
        <?php echo O::e($siteName) ?><br>
        <?php echo O::e($t('%1$s to %2$s (%3$s days)', $filter->from->format('Y-m-d'), $filter->to->format('Y-m-d'), $filter->days())) ?><br>
        <?php echo O::e($t('Type: %s', $typeFilter)) ?> &middot; <?php echo O::e($t('Notification: %s', $notificationFilter)) ?><br>
        <?php echo O::e($t('Generated %s', $generated->format('Y-m-d H:i'))) ?>
    </p>

    <table class="totals">
        <tr>
            <?php foreach ([
                $t('Shown') => $n($totals->impressions),
                $t('Clicks') => $n($totals->clicks),
                $t('Click rate') => StatsSummary::percent($totals->clickRate()),
                $t('Dismissed') => $n($totals->dismissals),
                $t('Dismissal rate') => StatsSummary::percent($totals->dismissalRate()),
            ] as $label => $value) { ?>
                <td><div class="label"><?php echo O::e($label) ?></div><div class="value"><?php echo O::e($value) ?></div></td>
            <?php } ?>
        </tr>
    </table>

    <?php if ($report->notifications === []) { ?>
        <p><?php echo O::e($t('There are no notifications to report on for these filters.')) ?></p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th><?php echo O::e($t('Notification')) ?></th>
                    <th class="text"><?php echo O::e($t('Type')) ?></th>
                    <th><?php echo O::e($t('Shown')) ?></th>
                    <th><?php echo O::e($t('Clicks')) ?></th>
                    <th><?php echo O::e($t('Click rate')) ?></th>
                    <th><?php echo O::e($t('Dismissed')) ?></th>
                    <th><?php echo O::e($t('Dismissal rate')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report->notifications as $row) { ?>
                    <tr>
                        <td><?php echo O::e($row['name']) ?></td>
                        <td class="text"><?php echo O::e($typeLabels[$row['type']] ?? $row['type']) ?></td>
                        <td><?php echo O::e($n($row['impressions'])) ?></td>
                        <td><?php echo O::e($n($row['clicks'])) ?></td>
                        <td><?php echo O::e(StatsSummary::percent($row['clickRate'])) ?></td>
                        <td><?php echo O::e($n($row['dismissals'])) ?></td>
                        <td><?php echo O::e(StatsSummary::percent($row['dismissalRate'])) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><?php echo O::e($t('Total')) ?></td>
                    <td class="text"></td>
                    <td><?php echo O::e($n($totals->impressions)) ?></td>
                    <td><?php echo O::e($n($totals->clicks)) ?></td>
                    <td><?php echo O::e(StatsSummary::percent($totals->clickRate())) ?></td>
                    <td><?php echo O::e($n($totals->dismissals)) ?></td>
                    <td><?php echo O::e(StatsSummary::percent($totals->dismissalRate())) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php } ?>

    <p class="note"><?php echo O::e($t('Counts are approximate: visitors using ad blockers are missed, and crawlers and signed-in administrators are not counted.')) ?></p>
</body>
</html>
