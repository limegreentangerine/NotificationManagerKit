<?php

namespace Concrete\Package\NotificationManagerKit\Controller\SinglePage\Dashboard\NotificationManager;

use Concrete\Core\Asset\AssetList;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Entity\File\File as FileEntity;
use NotificationManagerKit\Support\{Catalog, Schedule};
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\NotificationManagerKit\Controller\Search\Notifications as SearchController;
use NotificationManagerKit\Entity\{AbstractNotification, BannerNotification, NotificationContent, PopupNotification, Settings};

class Notifications extends DashboardPageController
{
    protected $helpers = [
        'form',
        'concrete/ui',
        'concrete/asset_library',
        'form/page_selector',
        'form/date_time',
    ];

    protected $locales;

    protected function findOrRedirect($id): AbstractNotification
    {
        $entity = is_string($id) && $id !== '' ? AbstractNotification::getByID($id) : false;

        if (!$entity) {
            $this->buildRedirect('/dashboard/notification_manager/notifications/')->send();
            exit;
        }

        return $entity;
    }

    protected function prepareForm(): void
    {
        AssetList::getInstance()->register('javascript', 'notification-manager-kit/dashboard', 'js/nm-dashboard.js', ['minify' => false, 'combine' => false], 'notification_manager_kit');
        $this->requireAsset('core/sitemap');
        $this->requireAsset('javascript', 'notification-manager-kit/dashboard');
        $this->set('scopes', array_map(fn($label) => t($label), AbstractNotification::DISPLAY_SCOPES));
        $this->set('repeatEvents', array_map(fn($label) => t($label), Schedule::REPEAT_EVENTS));
        $this->set('transitions', array_map(fn($label) => t($label), BannerNotification::TRANSITIONS));
        $this->set('frequencies', array_map(fn($label) => t($label), PopupNotification::FREQUENCIES));
        $this->set('scrollUnits', array_map(fn($label) => t($label), PopupNotification::SCROLL_UNITS));
    }

    protected function validate(array $post, string $type): void
    {
        $strings = $this->app->make('helper/validation/strings');
        $required = $this->requiredLanguage();

        if (!$strings->notempty($post['name'][$required] ?? '')) {
            $this->error->add(t('Please enter a name for the %s locale.', $required));
        }

        if (!$strings->notempty(strip_tags((string) ($post['content'][$required] ?? ''), '<img><video><iframe>'))) {
            $this->error->add(t('Please enter some content for the %s locale.', $required));
        }

        $dates = $this->app->make('helper/form/date_time');
        $start = $dates->translate('startDate', $post, true);
        $end = $dates->translate('endDate', $post, true);

        if ($start && $end && $start > $end) {
            $this->error->add(t('The start date must be before the end date.'));
        }

        if (($post['repeatEvent'] ?? 'none') === 'annually' && !$start) {
            $this->error->add(t('An annual repeat needs a start date.'));
        }

        if (($post['displayScope'] ?? 'all') === 'specific' && $this->postedPageIds($post) === []) {
            $this->error->add(t('Choose at least one page, or show on all pages.'));
        }

        if ($type === AbstractNotification::TYPE_POPUP) {
            $this->validatePopup($post);
        }
    }

    protected function validatePopup(array $post): void
    {
        $popup = (new PopupNotification())
            ->setTriggerOnLoad(isset($post['triggerOnLoad']))
            ->setTriggerOnScroll(isset($post['triggerOnScroll']))
            ->setTriggerOnScrollToElement(isset($post['triggerOnScrollToElement']))
            ->setTriggerScrollElementSelector($post['triggerScrollElementSelector'] ?? '')
            ->setTriggerOnClick(isset($post['triggerOnClick']))
            ->setTriggerClickSelector($post['triggerClickSelector'] ?? '');

        foreach ([
            ['triggerOnScrollToElement', 'triggerScrollElementSelector', t('scroll to element')],
            ['triggerOnClick', 'triggerClickSelector', t('click')],
        ] as [$flag, $field, $label]) {
            if (isset($post[$flag]) && trim((string) ($post[$field] ?? '')) !== '' && !\NotificationManagerKit\Support\SelectorValidator::isValid((string) $post[$field])) {
                $this->error->add(t('The %s selector is not valid. Use ids, classes, tags or [attribute] selectors only.', $label));
            }
        }

        if (!$popup->hasUsableTrigger()) {
            $this->error->add(t('Choose at least one trigger, with a valid selector where one is needed.'));
        }
    }

    /**
     * Content must exist for the default language when the site has it, otherwise for the first locale.
     */
    protected function requiredLanguage(): string
    {
        $languages = array_map(fn($locale) => $locale->getLanguage(), $this->locales);
        $default = Settings::get()->getDefaultLanguage();

        return in_array($default, $languages, true) ? $default : (string) ($languages[0] ?? $default);
    }

    /**
     * @return int[]
     */
    protected function postedPageIds(array $post): array
    {
        return array_values(array_filter(array_map('intval', (array) ($post['pages'] ?? [])), fn(int $cID) => $cID > 0));
    }

    protected function apply(AbstractNotification $entity, array $post): void
    {
        $dates = $this->app->make('helper/form/date_time');

        $entity->setActive(isset($post['active']))
            ->setPriority($post['priority'] ?? 0)
            ->setStartDate($dates->translate('startDate', $post, true) ?: null)
            ->setEndDate($dates->translate('endDate', $post, true) ?: null)
            ->setRepeatEvent($post['repeatEvent'] ?? 'none')
            ->setDisplayScope($post['displayScope'] ?? 'all')
            ->setPageIds($entity->getDisplayScope() === 'specific' ? $this->postedPageIds($post) : []);

        if ($entity instanceof BannerNotification) {
            $entity->setTransition($post['transition'] ?? 'fade')
                ->setInterval($post['interval'] ?? BannerNotification::DEFAULT_INTERVAL)
                ->setDismissible(isset($post['dismissible']));
        }

        $images = [];
        if ($entity instanceof PopupNotification) {
            $entity->setFrequency($post['frequency'] ?? '')
                ->setTriggerOnLoad(isset($post['triggerOnLoad']))
                ->setTriggerDelayMs(((float) ($post['triggerDelay'] ?? 0)) * 1000)
                ->setTriggerOnScroll(isset($post['triggerOnScroll']))
                ->setTriggerScrollValue($post['triggerScrollValue'] ?? 0)
                ->setTriggerScrollUnit($post['triggerScrollUnit'] ?? 'percent')
                ->setTriggerOnScrollToElement(isset($post['triggerOnScrollToElement']))
                ->setTriggerScrollElementSelector($post['triggerScrollElementSelector'] ?? '')
                ->setTriggerOnClick(isset($post['triggerOnClick']))
                ->setTriggerClickSelector($post['triggerClickSelector'] ?? '');

            // All the posted images in one query rather than one per locale.
            $imageIds = array_values(array_unique(array_filter(array_map('intval', (array) ($post['image'] ?? [])))));
            if ($imageIds !== []) {
                foreach ($this->entityManager->getRepository(FileEntity::class)->findBy(['fID' => $imageIds]) as $file) {
                    $images[$file->getFileID()] = $file;
                }
            }
        }

        foreach ($this->locales as $locale) {
            $lang = $locale->getLanguage();
            $content = $entity->getContent($lang) ?: (new NotificationContent())->setLanguage($lang);

            $content->setName((string) ($post['name'][$lang] ?? ''))
                ->setContent((string) ($post['content'][$lang] ?? ''))
                ->setLinkText((string) ($post['linkText'][$lang] ?? ''))
                ->setLinkCID($post['linkCID'][$lang] ?? null);

            if ($entity instanceof PopupNotification) {
                $content->setImage($images[(int) ($post['image'][$lang] ?? 0)] ?? null);
            }

            $this->entityManager->persist($content);
            $entity->addData($content);
        }

        // Content lives on NotificationContent, so bump this explicitly: it is the version a visitor's
        // dismissal is tied to, and an edit should show the notification to them again.
        $entity->setDateUpdated(new \DateTimeImmutable());
    }

    /**
     * What the form shows: the saved entity, overlaid with the submitted values when a save failed validation.
     * Everything here is escaped where it is output, never here.
     *
     * @return array<string, mixed>
     */
    protected function formData(?AbstractNotification $entity, ?array $post): array
    {
        $popup = $entity instanceof PopupNotification ? $entity : new PopupNotification();
        $banner = $entity instanceof BannerNotification ? $entity : new BannerNotification();

        $dates = $this->app->make('helper/form/date_time');

        $v = [
            'id' => $entity?->getID() ?? '',
            'type' => $entity?->getType() ?? (string) ($post['type'] ?? AbstractNotification::TYPE_BANNER),
            'active' => $post !== null ? isset($post['active']) : ($entity?->isActive() ?? true),
            'priority' => (int) ($post['priority'] ?? $entity?->getPriority() ?? 0),
            'startDate' => $post !== null ? $dates->translate('startDate', $post, true) : $entity?->getStartDate(),
            'endDate' => $post !== null ? $dates->translate('endDate', $post, true) : $entity?->getEndDate(),
            'repeatEvent' => $post['repeatEvent'] ?? $entity?->getRepeatEvent() ?? 'none',
            'displayScope' => $post['displayScope'] ?? $entity?->getDisplayScope() ?? 'all',
            'pages' => $post !== null ? $this->postedPageIds($post) : ($entity?->getPageIds() ?? []),
            'transition' => $post['transition'] ?? $banner->getTransition(),
            'interval' => (int) ($post['interval'] ?? $banner->getInterval()),
            'dismissible' => $post !== null ? isset($post['dismissible']) : $banner->isDismissible(),
            'frequency' => $post['frequency'] ?? $popup->getFrequency(),
            'triggerOnLoad' => $post !== null ? isset($post['triggerOnLoad']) : $popup->isTriggerOnLoad(),
            'triggerDelay' => $post['triggerDelay'] ?? $popup->getTriggerDelayMs() / 1000,
            'triggerOnScroll' => $post !== null ? isset($post['triggerOnScroll']) : $popup->isTriggerOnScroll(),
            'triggerScrollValue' => (int) ($post['triggerScrollValue'] ?? $popup->getTriggerScrollValue()),
            'triggerScrollUnit' => $post['triggerScrollUnit'] ?? $popup->getTriggerScrollUnit(),
            'triggerOnScrollToElement' => $post !== null ? isset($post['triggerOnScrollToElement']) : $popup->isTriggerOnScrollToElement(),
            'triggerScrollElementSelector' => (string) ($post['triggerScrollElementSelector'] ?? $popup->getTriggerScrollElementSelector()),
            'triggerOnClick' => $post !== null ? isset($post['triggerOnClick']) : $popup->isTriggerOnClick(),
            'triggerClickSelector' => (string) ($post['triggerClickSelector'] ?? $popup->getTriggerClickSelector()),
            'locales' => [],
        ];

        foreach ($this->locales as $locale) {
            $lang = $locale->getLanguage();
            $content = $entity?->getContent($lang) ?: null;

            $v['locales'][$lang] = [
                'name' => (string) ($post['name'][$lang] ?? $content?->getName() ?? ''),
                'content' => (string) ($post['content'][$lang] ?? $content?->getContent(true) ?? ''),
                'linkText' => (string) ($post['linkText'][$lang] ?? $content?->getLinkText() ?? ''),
                'linkCID' => (int) ($post['linkCID'][$lang] ?? $content?->getLinkCID() ?? 0),
                'image' => $content ? $content->getImage() : false,
            ];
        }

        return $v;
    }

    public function on_start()
    {
        parent::on_start();

        $this->locales = $this->app->make('site')->getActiveSiteForEditing()->getLocales();
        $this->set('locales', $this->locales);
        $this->set('types', [
            AbstractNotification::TYPE_BANNER => t('Banner'),
            AbstractNotification::TYPE_POPUP => t('Popup'),
        ]);
    }

    public function view()
    {
        $reset = false;

        if ($this->request->isPost()) {
            if (!$this->token->validate('notification-search')) {
                $this->error->add($this->token->getErrorMessage());
            } else {
                $reset = true;
            }
        }

        $search = $this->app->make(SearchController::class);
        $search->search($reset);
        $result = $search->getSearchResultObject();

        $this->set('result', $result);
        $this->set('items', $result->getItems());
        $this->set('pagination', $result->getPaginationHTML());
    }

    public function add()
    {
        $this->prepareForm();
        $this->set('v', $this->formData(null, null));
    }

    public function details($id = false)
    {
        $entity = $this->findOrRedirect($id);

        // Every locale's content and image in one query; the form reads them per locale.
        $this->entityManager
            ->createQuery('SELECT n, d, f, p FROM ' . AbstractNotification::class . ' n LEFT JOIN n.data d LEFT JOIN d.fID f LEFT JOIN n.pages p WHERE n.id = :id')
            ->setParameter('id', $entity->getID())
            ->getResult();

        $this->prepareForm();
        $this->set('entity', $entity);
        $this->set('v', $this->formData($entity, null));
    }

    public function save()
    {
        $post = $this->request->request->all();
        $entity = null;

        if (!$this->token->validate('submit')) {
            $this->error->add($this->token->getErrorMessage());
        }

        if (!empty($post['id'])) {
            // An id that matches nothing is an error, never a silent "create a new one".
            $entity = AbstractNotification::getByID((string) $post['id']) ?: null;

            if (!$entity) {
                $this->error->add(t('Unable to find the specified notification.'));
            }
        } elseif (!AbstractNotification::create((string) ($post['type'] ?? ''))) {
            $this->error->add(t('Please choose a notification type.'));
        }

        $type = $entity ? $entity->getType() : (string) ($post['type'] ?? '');
        $this->validate($post, $type);

        if ($this->error->has()) {
            $this->prepareForm();
            $this->set('entity', $entity);
            $this->set('v', $this->formData($entity, $post));

            return;
        }

        $entity ??= AbstractNotification::create($type);
        $this->apply($entity, $post);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        Catalog::flush();

        $this->flash('success', t('Notification saved.'));
        $this->buildRedirect('/dashboard/notification_manager/notifications/')->send();
    }

    public function delete($id = false)
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        $id = (string) $id;

        if (!$this->token->validate('delete-notification-id-' . $id)) {
            $this->error->add($this->token->getErrorMessage());
        } else {
            $entity = AbstractNotification::getByID($id);

            if ($entity) {
                foreach ($entity->getData() as $content) {
                    $this->entityManager->remove($content);
                }
                $this->entityManager->remove($entity);
                $this->entityManager->flush();
                Catalog::flush();

                $this->flash('success', t('Notification deleted.'));

                return $rf->json(true);
            }

            $this->error->add(t('Unable to find the specified notification.'));
        }

        return $rf->json($this->error->jsonSerialize());
    }
}
