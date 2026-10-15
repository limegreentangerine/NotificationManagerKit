<?php

defined('C5_EXECUTE') or die('Access Denied.');

use NotificationManagerKit\Entity\AbstractNotification;
use NotificationManagerKit\Support\{Output as O, StatsSummary};

/**
 * @var NotificationManagerKit\Support\StatsFilter                       $filter
 * @var NotificationManagerKit\Support\StatsReport                       $report
 * @var array<string, array{name: string, type: string}>                 $notificationOptions
 * @var array<string, array{title: string, chart: array<string, mixed>}> $charts
 * @var bool                                                             $statsEnabled
 * @var int[]                                                            $presets
 */
$page = (string) View::url('/dashboard/notification_manager/statistics');
$totals = $report->totals;
$typeLabels = ['all' => t('All types'), AbstractNotification::TYPE_BANNER => t('Banners'), AbstractNotification::TYPE_POPUP => t('Popups')];
$typeNames = [AbstractNotification::TYPE_BANNER => t('Banner'), AbstractNotification::TYPE_POPUP => t('Popup')];

// A preset keeps whatever type and notification is chosen.
$carry = array_diff_key($filter->toQuery(), array_flip(['range', 'from', 'to']));
$presetUrl = fn(int $days): string => $page . '?' . http_build_query(['range' => $days] + $carry);
$exportUrl = fn(string $format): string => (string) View::url('/dashboard/notification_manager/statistics/export', $format) . '?' . http_build_query($filter->toQuery());

$tiles = [
    t('Shown') => number_format($totals->impressions),
    t('Clicks') => number_format($totals->clicks),
    t('Click rate') => StatsSummary::percent($totals->clickRate()),
    t('Dismissed') => number_format($totals->dismissals),
    t('Dismissal rate') => StatsSummary::percent($totals->dismissalRate()),
];
?>
<div class="ccm-dashboard-header-buttons">
    <div class="btn-group" role="group" aria-label="<?php echo O::e(t('Download')) ?>">
        <a href="<?php echo O::e($exportUrl('xlsx')) ?>" class="btn btn-secondary"><?php echo t('Excel') ?></a>
        <a href="<?php echo O::e($exportUrl('json')) ?>" class="btn btn-secondary"><?php echo t('JSON') ?></a>
        <a href="<?php echo O::e($exportUrl('pdf')) ?>" class="btn btn-secondary"><?php echo t('PDF') ?></a>
    </div>
</div>

<?php if (!$statsEnabled) { ?>
    <div class="alert alert-info"><?php echo t('Statistics are switched off, so nothing new is being recorded. Figures from before they were switched off are still shown. Turn them back on in Notification Manager settings.') ?></div>
<?php } ?>

<form method="get" action="<?php echo O::e($page) ?>" class="nm-stats-filter mb-4">
    <div class="btn-group mb-3" role="group" aria-label="<?php echo O::e(t('Date range')) ?>">
        <?php foreach ($presets as $days) { ?>
            <a href="<?php echo O::e($presetUrl($days)) ?>" class="btn <?php echo $filter->preset === $days ? 'btn-primary' : 'btn-outline-secondary' ?>"<?php echo $filter->preset === $days ? ' aria-current="true"' : '' ?>><?php echo O::e(t('Last %s days', $days)) ?></a>
        <?php } ?>
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-6 col-md-2">
            <label for="from" class="form-label"><?php echo t('From') ?></label>
            <input type="date" id="from" name="from" class="form-control" value="<?php echo O::e($filter->from->format('Y-m-d')) ?>" max="<?php echo O::e(date('Y-m-d')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label"><?php echo t('To') ?></label>
            <input type="date" id="to" name="to" class="form-control" value="<?php echo O::e($filter->to->format('Y-m-d')) ?>" max="<?php echo O::e(date('Y-m-d')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="type" class="form-label"><?php echo t('Type') ?></label>
            <select id="type" name="type" class="form-select">
                <?php foreach ($typeLabels as $value => $label) { ?>
                    <option value="<?php echo O::e($value) ?>"<?php echo $filter->type === $value ? ' selected' : '' ?>><?php echo O::e($label) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-6 col-md-4">
            <label for="notification" class="form-label"><?php echo t('Notification') ?></label>
            <select id="notification" name="notification" class="form-select">
                <option value=""><?php echo t('All notifications') ?></option>
                <?php foreach ($notificationOptions as $id => $option) { ?>
                    <option value="<?php echo O::e($id) ?>"<?php echo $filter->notificationId === $id ? ' selected' : '' ?>><?php echo O::e($option['name'] . ' (' . ($typeNames[$option['type']] ?? $option['type']) . ')') ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <button type="submit" class="btn btn-primary w-100"><?php echo t('Apply') ?></button>
        </div>
    </div>
    <div class="help-block form-text mt-2"><?php echo O::e(t('Showing %1$s to %2$s (%3$s days). Statistics are kept for the period set in settings, so older days may be empty.', $filter->from->format('Y-m-d'), $filter->to->format('Y-m-d'), $filter->days())) ?></div>
</form>

<div class="row g-3 mb-4">
    <?php foreach ($tiles as $label => $value) { ?>
        <div class="col-6 col-md">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo O::e($label) ?></div>
                <div class="fs-3 fw-semibold"><?php echo O::e($value) ?></div>
            </div>
        </div>
    <?php } ?>
</div>

<?php if ($report->notifications === []) { ?>
    <div class="alert alert-info">
        <?php echo $filter->notificationId !== '' || $filter->type !== 'all'
            ? t('No notifications match these filters.')
            : t('No notifications yet. Add a banner or a popup to get started.') ?>
    </div>
<?php } elseif (!$report->hasActivity()) { ?>
    <div class="alert alert-info"><?php echo t('Nothing was recorded for this period. Figures appear once visitors have seen a notification.') ?></div>
<?php } ?>

<?php if ($report->notifications !== []) { ?>
    <?php foreach ($charts as $field => $item) { ?>
        <?php View::element('dashboard/statistics/bar_chart', ['title' => $item['title'], 'chart' => $item['chart'], 'total' => $totals->{$field}], 'notification_manager_kit') ?>
    <?php } ?>

    <h3 class="h5 mt-4"><?php echo t('By notification') ?></h3>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th><?php echo t('Notification') ?></th>
                    <th><?php echo t('Type') ?></th>
                    <th class="text-end"><?php echo t('Shown') ?></th>
                    <th class="text-end"><?php echo t('Clicks') ?></th>
                    <th class="text-end"><?php echo t('Click rate') ?></th>
                    <th class="text-end"><?php echo t('Dismissed') ?></th>
                    <th class="text-end"><?php echo t('Dismissal rate') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report->notifications as $row) { ?>
                    <tr>
                        <td><a href="<?php echo O::e(View::url('/dashboard/notification_manager/notifications/details', $row['id'])) ?>"><?php echo O::e($row['name']) ?></a></td>
                        <td><?php echo O::e($typeNames[$row['type']] ?? $row['type']) ?></td>
                        <td class="text-end"><?php echo O::e(number_format($row['impressions'])) ?></td>
                        <td class="text-end"><?php echo O::e(number_format($row['clicks'])) ?></td>
                        <td class="text-end"><?php echo O::e(StatsSummary::percent($row['clickRate'])) ?></td>
                        <td class="text-end"><?php echo O::e(number_format($row['dismissals'])) ?></td>
                        <td class="text-end"><?php echo O::e(StatsSummary::percent($row['dismissalRate'])) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr class="fw-semibold">
                    <td><?php echo t('Total') ?></td>
                    <td></td>
                    <td class="text-end"><?php echo O::e(number_format($totals->impressions)) ?></td>
                    <td class="text-end"><?php echo O::e(number_format($totals->clicks)) ?></td>
                    <td class="text-end"><?php echo O::e(StatsSummary::percent($totals->clickRate())) ?></td>
                    <td class="text-end"><?php echo O::e(number_format($totals->dismissals)) ?></td>
                    <td class="text-end"><?php echo O::e(StatsSummary::percent($totals->dismissalRate())) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <details class="mb-4">
        <summary><?php echo t('Show the daily figures') ?></summary>
        <div class="table-responsive mt-2">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th><?php echo t('Day') ?></th>
                        <th class="text-end"><?php echo t('Shown') ?></th>
                        <th class="text-end"><?php echo t('Clicks') ?></th>
                        <th class="text-end"><?php echo t('Dismissed') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($totals->daily, true) as $day => $counts) { ?>
                        <tr>
                            <td><?php echo O::e($day) ?></td>
                            <td class="text-end"><?php echo O::e(number_format($counts['impressions'])) ?></td>
                            <td class="text-end"><?php echo O::e(number_format($counts['clicks'])) ?></td>
                            <td class="text-end"><?php echo O::e(number_format($counts['dismissals'])) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </details>

    <div class="help-block form-text"><?php echo t('Counts are approximate: visitors using ad blockers are missed, and crawlers and signed-in administrators are not counted. The Excel and JSON downloads also list every day for every notification; the PDF has the totals and the table above.') ?></div>
<?php } ?>
