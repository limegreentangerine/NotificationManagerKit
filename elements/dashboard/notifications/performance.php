<?php

defined('C5_EXECUTE') or die('Access Denied.');

use NotificationManagerKit\Support\Output as O;
use NotificationManagerKit\Support\StatsSummary;

/**
 * @var StatsSummary|null $stats null when statistics are off or could not be read
 * @var bool              $statsEnabled
 * @var int               $statsDays
 */

if (!$statsEnabled) { ?>
    <div class="alert alert-info"><?php echo t('Statistics are switched off. Turn them on in Notification Manager settings to see how this notification performs.') ?></div>
    <?php return;
}

if ($stats === null) { ?>
    <div class="alert alert-warning"><?php echo t('Statistics could not be read.') ?></div>
    <?php return;
}

$tiles = [
    t('Shown') => number_format($stats->impressions),
    t('Clicks') => number_format($stats->clicks),
    t('Click rate') => StatsSummary::percent($stats->clickRate()),
    t('Dismissed') => number_format($stats->dismissals),
    t('Dismissal rate') => StatsSummary::percent($stats->dismissalRate()),
];
$recent = array_slice(array_reverse($stats->daily, true), 0, 14, true);
$peak = max(1, ...array_column($recent, 'impressions'));
?>
<fieldset class="mb-4">
    <legend><?php echo O::e(t('Performance, last %s days', $statsDays)) ?></legend>

    <div class="row g-3 mb-3">
        <?php foreach ($tiles as $label => $value) { ?>
            <div class="col-6 col-md">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small"><?php echo O::e($label) ?></div>
                    <div class="fs-4 fw-semibold"><?php echo O::e($value) ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <?php if ($stats->impressions === 0 && $stats->clicks === 0 && $stats->dismissals === 0) { ?>
        <p class="text-muted"><?php echo t('Nothing recorded yet. Figures appear once visitors have seen this notification.') ?></p>
    <?php } else { ?>
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th><?php echo t('Day') ?></th>
                    <th class="w-50"><?php echo t('Shown') ?></th>
                    <th class="text-end"><?php echo t('Clicks') ?></th>
                    <th class="text-end"><?php echo t('Dismissed') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $day => $counts) { ?>
                    <tr>
                        <td><?php echo O::e($day) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary rounded" style="height: .6rem; width: <?php echo (int) round($counts['impressions'] / $peak * 100) ?>%; min-width: <?php echo $counts['impressions'] > 0 ? '2px' : '0' ?>"></div>
                                <span class="small"><?php echo O::e(number_format($counts['impressions'])) ?></span>
                            </div>
                        </td>
                        <td class="text-end"><?php echo O::e(number_format($counts['clicks'])) ?></td>
                        <td class="text-end"><?php echo O::e(number_format($counts['dismissals'])) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>

    <div class="help-block form-text"><?php echo t('Counts are approximate: visitors using ad blockers are missed, and crawlers and signed-in administrators are not counted. The table shows the most recent 14 days.') ?></div>
</fieldset>
