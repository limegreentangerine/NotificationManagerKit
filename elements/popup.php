<?php

defined('C5_EXECUTE') or die('Access Denied.');

use NotificationManagerKit\Support\Output;

/** @var NotificationManagerKit\Support\NotificationView $popup */
$o = $popup->options;
$titleId = 'nm-popup-title-' . preg_replace('/[^A-Za-z0-9_-]/', '', $popup->id);
$config = [
    'id' => $popup->id,
    'version' => $popup->version,
    'frequency' => $o['frequency'] ?? 'every_time_until_close',
    'onLoad' => !empty($o['onLoad']),
    'delay' => (int) ($o['delay'] ?? 0),
    'onScroll' => !empty($o['onScroll']),
    'scrollValue' => (int) ($o['scrollValue'] ?? 0),
    'scrollUnit' => ($o['scrollUnit'] ?? 'percent') === 'px' ? 'px' : 'percent',
    'scrollElement' => (string) ($o['scrollElement'] ?? ''),
    'click' => (string) ($o['click'] ?? ''),
];
?>
<nm-popup data-config="<?php echo Output::e(Output::json($config)) ?>">
    <dialog class="nm-popup" aria-labelledby="<?php echo Output::e($titleId) ?>">
        <form method="dialog" class="nm-popup__close-form">
            <button class="nm-popup__close" aria-label="<?php echo Output::e(t('Close')) ?>"></button>
        </form>
        <div class="nm-popup__body">
            <?php if ($popup->image) { ?>
                <?php $image = $popup->image; ?>
                <div class="nm-popup__media">
                    <img src="<?php echo Output::url($image['url']) ?>" alt="<?php echo Output::e($image['alt']) ?>"
                        <?php echo $image['width'] > 0 && $image['height'] > 0 ? 'width="' . (int) $image['width'] . '" height="' . (int) $image['height'] . '"' : '' ?>
                        loading="lazy" decoding="async">
                </div>
            <?php } ?>
            <div class="nm-popup__text">
                <h2 class="nm-popup__title" id="<?php echo Output::e($titleId) ?>"><?php echo Output::e($popup->name) ?></h2>
                <div class="nm-popup__content"><?php echo $popup->contentHtml ?></div>
                <?php if ($popup->linkUrl !== '' && $popup->linkText !== '') { ?>
                    <a class="nm-popup__link" href="<?php echo Output::url($popup->linkUrl) ?>"><?php echo Output::e($popup->linkText) ?></a>
                <?php } ?>
            </div>
        </div>
    </dialog>
</nm-popup>
