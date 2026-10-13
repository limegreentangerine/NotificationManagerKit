<?php

defined('C5_EXECUTE') or die('Access Denied.');

use NotificationManagerKit\Support\Output;

/** @var NotificationManagerKit\Support\NotificationView[] $banners */
$lead = $banners[0];
$transition = in_array($lead->options['transition'] ?? '', ['fade', 'scroll-vertical', 'ticker'], true) ? $lead->options['transition'] : 'fade';
$interval = max(2000, min(60000, (int) ($lead->options['interval'] ?? 6000)));
$rotating = count($banners) > 1;
?>
<nm-banner class="nm-banner" role="region" aria-label="<?php echo Output::e(t('Announcements')) ?>" data-transition="<?php echo Output::e($transition) ?>" data-interval="<?php echo (int) $interval ?>"<?php echo $rotating ? ' data-rotating' : '' ?>>
    <div class="nm-banner__track">
        <?php foreach ($banners as $i => $banner) { ?>
            <div class="nm-banner__item<?php echo $i === 0 ? ' is-active' : '' ?>" data-id="<?php echo Output::e($banner->id) ?>" data-version="<?php echo (int) $banner->version ?>">
                <div class="nm-banner__content">
                    <?php echo $banner->contentHtml ?>
                    <?php if ($banner->linkUrl !== '' && $banner->linkText !== '') { ?>
                        <a class="nm-banner__link" href="<?php echo Output::url($banner->linkUrl) ?>"><?php echo Output::e($banner->linkText) ?></a>
                    <?php } ?>
                </div>
                <?php if (!empty($banner->options['dismissible'])) { ?>
                    <button type="button" class="nm-banner__close" data-nm-close aria-label="<?php echo Output::e(t('Dismiss')) ?>"></button>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</nm-banner>
