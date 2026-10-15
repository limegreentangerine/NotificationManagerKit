<?php

defined('C5_EXECUTE') or die('Access Denied.');

use NotificationManagerKit\Support\{Output as O, StatsChart};

/**
 * One measure over time as a column chart. Plain HTML and CSS, so it scales without distorting its labels, and every
 * column is a full-height hover target with a native tooltip. The same figures are in the table beneath the charts.
 *
 * @var string                                                                                                        $title
 * @var int                                                                                                           $total
 * @var array{bars: list<array{label: string, value: int, height: float}>, max: int, ticks: list<int>, grouped: bool} $chart
 */
$bars = $chart['bars'];
$first = $bars[0]['label'] ?? '';
$last = $bars[count($bars) - 1]['label'] ?? '';
?>
<figure class="nm-chart" role="img" aria-label="<?php echo O::e(t('%1$s: %2$s in total', $title, number_format($total))) ?>">
    <figcaption>
        <span><?php echo O::e($title) ?><?php echo $chart['grouped'] ? ' <span class="nm-chart__note">' . O::e(t('(by week)')) . '</span>' : '' ?></span>
        <span class="nm-chart__total"><?php echo O::e(number_format($total)) ?></span>
    </figcaption>

    <div class="nm-chart__body">
        <div class="nm-chart__axis" aria-hidden="true">
            <?php foreach ($chart['ticks'] as $tick) { ?>
                <span style="bottom: <?php echo round($tick / $chart['max'] * 100, 2) ?>%"><?php echo O::e(StatsChart::compact($tick)) ?></span>
            <?php } ?>
        </div>

        <div class="nm-chart__plot">
            <div class="nm-chart__grid" aria-hidden="true">
                <?php foreach ($chart['ticks'] as $tick) { ?>
                    <span style="bottom: <?php echo round($tick / $chart['max'] * 100, 2) ?>%"></span>
                <?php } ?>
            </div>

            <div class="nm-chart__bars">
                <?php foreach ($bars as $bar) { ?>
                    <div class="nm-chart__col" title="<?php echo O::e($bar['label'] . ': ' . number_format($bar['value'])) ?>">
                        <div class="nm-chart__bar<?php echo $bar['value'] > 0 ? '' : ' is-empty' ?>" style="height: <?php echo round($bar['height'] * 100, 2) ?>%"></div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="nm-chart__x" aria-hidden="true">
        <span><?php echo O::e($first) ?></span>
        <span><?php echo O::e($last) ?></span>
    </div>
</figure>
