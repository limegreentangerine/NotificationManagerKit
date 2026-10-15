<?php

defined('C5_EXECUTE') or die('Access Denied.');

use NotificationManagerKit\Support\Output as O;

/**
 * @var bool                                                $statsEnabled
 * @var int                                                 $statsDays
 * @var array<string, array{impressions: int, clicks: int}> $statTotals
 */
?>
<div class="ccm-dashboard-header-buttons">
    <a href="<?php echo O::e(View::url('/dashboard/notification_manager/notifications/add/')) ?>" class="btn btn-primary"><?php echo t('Add Notification') ?></a>
</div>

<div class="ccm-dashboard-content-full">
    <?php if (empty($items)) { ?>
        <div class="alert alert-info m-3">
            <?php echo t('No notifications yet. Add a banner or a popup to get started.') ?>
        </div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="ccm-search-results-table">
                <thead>
                    <tr>
                        <?php foreach ($result->getColumns() as $column) { ?>
                            <?php if ($column->isColumnSortable()) { ?>
                                <th class="<?php echo O::e($column->getColumnStyleClass()) ?>">
                                    <a href="<?php echo O::e($column->getColumnSortURL()) ?>"><?php echo O::e($column->getColumnTitle()) ?></a>
                                </th>
                            <?php } else { ?>
                                <th><span><?php echo O::e($column->getColumnTitle()) ?></span></th>
                            <?php } ?>
                        <?php } ?>
                        <?php if ($statsEnabled) { ?>
                            <th><span><?php echo O::e(t('Views (%s days)', $statsDays)) ?></span></th>
                            <th><span><?php echo O::e(t('Clicks (%s days)', $statsDays)) ?></span></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) { ?>
                        <tr data-details-url="<?php echo O::e($item->getViewUrl()) ?>">
                            <?php foreach ($item->getColumns() as $column) { ?>
                                <td><?php echo O::e($column->getColumnValue()) ?></td>
                            <?php } ?>
                            <?php if ($statsEnabled) { ?>
                                <?php $totals = $statTotals[$item->getID()] ?? ['impressions' => 0, 'clicks' => 0]; ?>
                                <td><?php echo O::e(number_format($totals['impressions'])) ?></td>
                                <td><?php echo O::e(number_format($totals['clicks'])) ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination) { ?>
            <div class="ccm-search-results-pagination"><?php echo $pagination ?></div>
        <?php } ?>
    <?php } ?>
</div>
