<?php

defined('C5_EXECUTE') or die('Access Denied.');

use NotificationManagerKit\Support\Output as O;

/**
 * @var array<string, mixed> $v form values (never trusted: everything is escaped where it is output)
 */
$form = \Core::make('form/helper');
$editor = Core::make('editor');
$editor->setAllowFileManager(false);
?>

<?php if ($v['id'] !== '') { ?>
    <div class="ccm-dashboard-header-buttons">
        <button type="button" class="btn btn-danger" data-nm-delete
            data-dialog-title="<?php echo O::e(t('Confirm Delete')) ?>"
            data-redirect="<?php echo O::e((string) URL::to('/dashboard/notification_manager/notifications')) ?>">
            <?php echo t('Delete') ?>
        </button>
    </div>

    <div class="d-none ccm-ui" data-nm-delete-dialog>
        <form data-dialog-form="delete-form" method="POST" action="<?php echo O::e($view->action('delete', $v['id'])) ?>">
            <?php echo $token->output('delete-notification-id-' . $v['id']) ?>
            <p><?php echo t('Are you sure you want to permanently delete this notification?') ?></p>
            <p><strong><?php echo t('WARNING: this operation cannot be undone!') ?></strong></p>
        </form>
        <div class="dialog-buttons">
            <button type="button" class="btn btn-secondary float-start" data-dialog-action="cancel"><?php echo t('Cancel') ?></button>
            <button type="button" class="btn btn-danger float-end" data-dialog-action="submit"><?php echo t('Delete') ?></button>
        </div>
    </div>
<?php } ?>

<form method="post" action="<?php echo O::e($view->action('save')) ?>" data-nm-form>
    <?php echo $token->output('submit') ?>
    <?php if ($v['id'] !== '') { ?>
        <?php echo $form->hidden('id', $v['id']) ?>
    <?php } ?>

    <fieldset>
        <legend><?php echo t('Type') ?></legend>

        <div class="form-group mb-3">
            <?php if ($v['id'] !== '') { ?>
                <p class="form-control-plaintext"><?php echo O::e($types[$v['type']] ?? $v['type']) ?></p>
                <?php echo $form->hidden('type', $v['type']) ?>
                <div class="help-block form-text"><?php echo t('The type cannot be changed once a notification has been created.') ?></div>
            <?php } else { ?>
                <?php echo $form->label('type', t('Notification type')) ?>
                <?php echo $form->select('type', $types, $v['type']) ?>
            <?php } ?>
        </div>
    </fieldset>

    <fieldset>
        <legend><?php echo t('Status') ?></legend>

        <div class="form-check mb-3">
            <?php echo $form->checkbox('active', '1', $v['active']) ?>
            <?php echo $form->label('active', t('Active'), ['class' => 'form-check-label']) ?>
        </div>

        <div class="form-group mb-3">
            <?php echo $form->label('priority', t('Priority')) ?>
            <?php echo $form->number('priority', (int) $v['priority'], ['min' => -1000, 'max' => 1000]) ?>
            <div class="help-block form-text"><?php echo t('Higher is shown first. When several popups are eligible, only the highest priority is shown.') ?></div>
        </div>
    </fieldset>

    <fieldset>
        <legend><?php echo t('Scheduling') ?></legend>

        <div class="form-group mb-3">
            <?php echo $form->label('startDate', t('Start Date/Time')) ?>
            <?php echo $h['dt']->datetime('startDate', $v['startDate'] ?: '') ?>
            <div class="help-block form-text"><?php echo t('Leave blank to start immediately.') ?></div>
        </div>

        <div class="form-group mb-3">
            <?php echo $form->label('endDate', t('End Date/Time')) ?>
            <?php echo $h['dt']->datetime('endDate', $v['endDate'] ?: '') ?>
            <div class="help-block form-text"><?php echo t('Leave blank to run indefinitely.') ?></div>
        </div>

        <div class="form-group mb-3">
            <?php echo $form->label('repeatEvent', t('Repeat')) ?>
            <?php echo $form->select('repeatEvent', $repeatEvents, $v['repeatEvent']) ?>
            <div class="help-block form-text"><?php echo t('Narrows the start/end window to particular days.') ?></div>
        </div>
    </fieldset>

    <fieldset>
        <legend><?php echo t('Show On') ?></legend>

        <div class="form-group mb-3">
            <?php echo $form->label('displayScope', t('Show on')) ?>
            <?php echo $form->select('displayScope', $scopes, $v['displayScope']) ?>
        </div>

        <div class="form-group mb-3" data-nm-scope="specific">
            <?php echo $form->label('pages', t('Pages')) ?>
            <?php echo $h['ps']->selectMultipleFromSitemap('pages', $v['pages']) ?>
        </div>
    </fieldset>

    <fieldset data-nm-type="banner">
        <legend><?php echo t('Banner Options') ?></legend>

        <div class="form-group mb-3">
            <?php echo $form->label('transition', t('Rotation')) ?>
            <?php echo $form->select('transition', $transitions, $v['transition']) ?>
            <div class="help-block form-text"><?php echo t('How several banners take turns. The highest priority banner decides.') ?></div>
        </div>

        <div class="form-group mb-3">
            <?php echo $form->label('interval', t('Time per banner (milliseconds)')) ?>
            <?php echo $form->number('interval', (int) $v['interval'], ['min' => 2000, 'max' => 60000, 'step' => 500]) ?>
        </div>

        <div class="form-check mb-3">
            <?php echo $form->checkbox('dismissible', '1', $v['dismissible']) ?>
            <?php echo $form->label('dismissible', t('Visitors can dismiss this banner'), ['class' => 'form-check-label']) ?>
            <div class="help-block form-text"><?php echo t('A dismissed banner stays hidden until it is edited.') ?></div>
        </div>
    </fieldset>

    <fieldset data-nm-type="popup">
        <legend><?php echo t('Popup Options') ?></legend>

        <div class="form-group mb-3">
            <?php echo $form->label('frequency', t('How often a visitor sees it')) ?>
            <?php echo $form->select('frequency', $frequencies, $v['frequency']) ?>
        </div>

        <p class="mb-2"><?php echo t('Open the popup when…') ?></p>

        <div class="form-check mb-2">
            <?php echo $form->checkbox('triggerOnLoad', '1', $v['triggerOnLoad']) ?>
            <?php echo $form->label('triggerOnLoad', t('the page loads'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="form-group mb-3 ms-4">
            <?php echo $form->label('triggerDelay', t('after a delay of (seconds)')) ?>
            <?php echo $form->number('triggerDelay', $v['triggerDelay'], ['min' => 0, 'max' => 60, 'step' => 0.5]) ?>
        </div>

        <div class="form-check mb-2">
            <?php echo $form->checkbox('triggerOnScroll', '1', $v['triggerOnScroll']) ?>
            <?php echo $form->label('triggerOnScroll', t('the visitor scrolls'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="row g-2 mb-3 ms-3">
            <div class="col-auto">
                <?php echo $form->number('triggerScrollValue', (int) $v['triggerScrollValue'], ['min' => 0, 'max' => 100000, 'aria-label' => t('Scroll distance')]) ?>
            </div>
            <div class="col-auto">
                <?php echo $form->select('triggerScrollUnit', $scrollUnits, $v['triggerScrollUnit'], ['aria-label' => t('Scroll unit')]) ?>
            </div>
        </div>

        <div class="form-check mb-2">
            <?php echo $form->checkbox('triggerOnScrollToElement', '1', $v['triggerOnScrollToElement']) ?>
            <?php echo $form->label('triggerOnScrollToElement', t('an element scrolls into view'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="form-group mb-3 ms-4">
            <?php echo $form->text('triggerScrollElementSelector', $v['triggerScrollElementSelector'], ['maxlength' => 200, 'placeholder' => '#pricing', 'aria-label' => t('Element selector')]) ?>
        </div>

        <div class="form-check mb-2">
            <?php echo $form->checkbox('triggerOnClick', '1', $v['triggerOnClick']) ?>
            <?php echo $form->label('triggerOnClick', t('an element is clicked'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="form-group mb-3 ms-4">
            <?php echo $form->text('triggerClickSelector', $v['triggerClickSelector'], ['maxlength' => 200, 'placeholder' => '.open-popup', 'aria-label' => t('Element selector')]) ?>
            <div class="help-block form-text"><?php echo t('Selectors may use ids, classes, tags and [attribute] only.') ?></div>
        </div>
    </fieldset>

    <?php foreach ($locales as $locale) { ?>
        <?php
        $lang = $locale->getLanguage();
        $c = $v['locales'][$lang];
        $label = $locale->getLanguageText();
        ?>
        <fieldset>
            <legend><?php echo O::e($label) ?></legend>

            <div class="form-group mb-3">
                <?php echo $form->label('name[' . $lang . ']', t('Name (%s)', O::e($label))) ?>
                <?php echo $form->text('name[' . $lang . ']', $c['name'], ['maxlength' => 255]) ?>
            </div>

            <div class="form-group mb-3" data-nm-type="popup">
                <?php echo $form->label('image_' . $lang, t('Image (%s)', O::e($label))) ?>
                <?php echo $h['al']->image('image_' . $lang, 'image[' . $lang . ']', t('Choose Image'), $c['image'] ?: null) ?>
            </div>

            <div class="form-group mb-3">
                <?php echo $form->label('content[' . $lang . ']', t('Content (%s)', O::e($label))) ?>
                <?php // The editor writes this straight into a <textarea>, so it must be escaped here.?>
                <?php echo $editor->outputStandardEditor('content[' . $lang . ']', O::e($c['content'])) ?>
            </div>

            <div class="form-group mb-3">
                <?php echo $form->label('linkCID[' . $lang . ']', t('Linked Page (%s)', O::e($label))) ?>
                <?php echo $h['ps']->selectPage('linkCID[' . $lang . ']', $c['linkCID'] ?: false) ?>
            </div>

            <div class="form-group mb-3">
                <?php echo $form->label('linkText[' . $lang . ']', t('Link Text (%s)', O::e($label))) ?>
                <?php echo $form->text('linkText[' . $lang . ']', $c['linkText'], ['maxlength' => 255]) ?>
                <div class="help-block form-text"><?php echo t('Leave blank to use the linked page\'s name.') ?></div>
            </div>
        </fieldset>
    <?php } ?>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?php echo O::e((string) URL::to('/dashboard/notification_manager/notifications')) ?>" class="btn btn-secondary float-start"><?php echo t('Cancel') ?></a>
            <?php echo $form->submit('save', $v['id'] !== '' ? t('Update Notification') : t('Save Notification'), ['class' => 'btn btn-primary float-end']) ?>
        </div>
    </div>
</form>
