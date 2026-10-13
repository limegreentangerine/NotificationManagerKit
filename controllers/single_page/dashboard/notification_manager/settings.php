<?php

namespace Concrete\Package\NotificationManagerKit\Controller\SinglePage\Dashboard\NotificationManager;

use NotificationManagerKit\Support\Catalog;
use Concrete\Core\Page\Controller\DashboardPageController;
use NotificationManagerKit\Entity\Settings as SettingsEntity;

class Settings extends DashboardPageController
{
    public $helpers = [
        'form',
        'concrete/ui',
    ];

    /**
     * @return array<string, string> language code => label, for the active site's locales
     */
    protected function languages(): array
    {
        $languages = [];

        foreach ($this->app->make('site')->getActiveSiteForEditing()->getLocales() as $locale) {
            $languages[$locale->getLanguage()] = $locale->getLanguageText();
        }

        return $languages;
    }

    public function view()
    {
        $this->set('settings', SettingsEntity::get());
        $this->set('languages', $this->languages());
    }

    public function save()
    {
        if (!$this->request->isPost()) {
            return $this->buildRedirect('/dashboard/notification_manager/settings')->send();
        }

        if (!$this->token->validate('submit')) {
            $this->flash('error', $this->token->getErrorMessage());

            return $this->buildRedirect('/dashboard/notification_manager/settings')->send();
        }

        $language = (string) $this->post('defaultLanguage');

        if (!isset($this->languages()[$language])) {
            $this->flash('error', t('Please choose one of the site\'s languages as the default.'));

            return $this->buildRedirect('/dashboard/notification_manager/settings')->send();
        }

        $settings = SettingsEntity::get();
        $settings->setBannersEnabled($this->post('bannersEnabled') !== null)
            ->setPopupsEnabled($this->post('popupsEnabled') !== null)
            ->setUseCustomCss($this->post('useCustomCss') !== null)
            ->setExcludedPathsText((string) $this->post('excludedPaths'))
            ->setDefaultLanguage($language);

        $this->entityManager->persist($settings);
        $this->entityManager->flush();
        Catalog::flush();

        $this->flash('success', t('Settings saved.'));

        return $this->buildRedirect('/dashboard/notification_manager/settings')->send();
    }
}
