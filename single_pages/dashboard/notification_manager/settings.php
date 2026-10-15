<?php defined('C5_EXECUTE') or die('Access Denied.');

use NotificationManagerKit\Support\Output as O;

/** @var NotificationManagerKit\Entity\Settings $settings */
?>
<form method="post" action="<?php echo O::e($view->action('save')) ?>">
    <?php echo $token->output('submit') ?>

    <fieldset>
        <legend><?php echo t('Display') ?></legend>

        <div class="form-check mb-2">
            <input type="checkbox" id="bannersEnabled" name="bannersEnabled" class="form-check-input" value="1" <?php echo $settings->areBannersEnabled() ? 'checked' : '' ?>>
            <label for="bannersEnabled" class="form-check-label"><?php echo t('Show banners') ?></label>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" id="popupsEnabled" name="popupsEnabled" class="form-check-input" value="1" <?php echo $settings->arePopupsEnabled() ? 'checked' : '' ?>>
            <label for="popupsEnabled" class="form-check-label"><?php echo t('Show popups') ?></label>
            <div class="help-block form-text"><?php echo t('When several popups are eligible for a page, only the one with the highest priority is shown. Any number of banners can show at once.') ?></div>
        </div>
    </fieldset>

    <fieldset>
        <legend><?php echo t('Content') ?></legend>

        <div class="form-group mb-3">
            <label for="defaultLanguage" class="form-label"><?php echo t('Default language') ?></label>
            <?php echo $form->select('defaultLanguage', $languages, $settings->getDefaultLanguage()) ?>
            <div class="help-block form-text"><?php echo t('Used when a notification has no content for the visitor\'s language.') ?></div>
        </div>

        <div class="form-group mb-3">
            <label for="excludedPaths" class="form-label"><?php echo t('Never show on these paths') ?></label>
            <textarea id="excludedPaths" name="excludedPaths" class="form-control" rows="4"><?php echo O::e($settings->getExcludedPathsText()) ?></textarea>
            <div class="help-block form-text"><?php echo t('One path per line, eg. /checkout. Child pages are excluded too. The dashboard, login, registration and two-factor pages are always excluded.') ?></div>
        </div>
    </fieldset>

    <fieldset>
        <legend><?php echo t('Statistics') ?></legend>

        <div class="form-check mb-3">
            <input type="checkbox" id="statsEnabled" name="statsEnabled" class="form-check-input" value="1" <?php echo $settings->areStatsEnabled() ? 'checked' : '' ?>>
            <label for="statsEnabled" class="form-check-label"><?php echo t('Count impressions, clicks and dismissals') ?></label>
            <div class="help-block form-text"><?php echo t('Daily totals per notification and language, shown on each notification and in the list. Nothing identifies a visitor and nothing is stored in their browser for this. Crawlers and signed-in administrators are not counted. Turn this off and no tracking code is sent to visitors at all.') ?></div>
        </div>

        <div class="form-group mb-3">
            <label for="statsRetentionDays" class="form-label"><?php echo t('Keep statistics for (days)') ?></label>
            <input type="number" id="statsRetentionDays" name="statsRetentionDays" class="form-control" min="<?php echo (int) $settings::MIN_RETENTION_DAYS ?>" max="<?php echo (int) $settings::MAX_RETENTION_DAYS ?>" step="1" value="<?php echo (int) $settings->getStatsRetentionDays() ?>">
            <div class="help-block form-text"><?php echo t('Older totals are removed by the "Prune Notification Statistics" task. Schedule it to run daily from the Automation section of the dashboard.') ?></div>
        </div>
    </fieldset>

    <fieldset>
        <legend><?php echo t('Styling') ?></legend>

        <div class="form-check mb-3">
            <input type="checkbox" id="useCustomCss" name="useCustomCss" class="form-check-input" value="1" <?php echo $settings->usesCustomCss() ? 'checked' : '' ?>>
            <label for="useCustomCss" class="form-check-label"><?php echo t('Use my own CSS') ?></label>
            <div class="help-block form-text"><?php echo t('Skips the bundled stylesheets. Banners render as <nm-banner> and popups as <nm-popup> with a native dialog.') ?></div>
        </div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <?php echo $form->submit('save', t('Save Settings'), ['class' => 'btn btn-primary float-end']) ?>
        </div>
    </div>
</form>
