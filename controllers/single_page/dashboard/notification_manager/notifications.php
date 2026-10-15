<?php

namespace Concrete\Package\NotificationManagerKit\Controller\SinglePage\Dashboard\NotificationManager;

use Concrete\Core\Asset\AssetList;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Page\Controller\DashboardPageController;
use NotificationManagerKit\Support\{Audience, Catalog, LinkResolver, Schedule, StatsStore};
use Concrete\Package\NotificationManagerKit\Controller\Search\Notifications as SearchController;
use NotificationManagerKit\Entity\{AbstractNotification, BannerNotification, NotificationContent, PopupNotification, Settings};

class Notifications extends DashboardPageController
{
    /** The window the list and the details page report over. */
    protected const STATS_DAYS = 30;
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
        $this->set('linkTypes', array_map(fn($label) => t($label), LinkResolver::TYPES));
        $this->set('visitors', array_map(fn($label) => t($label), Audience::VISITORS));
        $this->set('deviceTypes', array_map(fn($label) => t($label), Audience::DEVICES));
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

        if (!AbstractNotification::isValidPriority($post['priority'] ?? null)) {
            $this->error->add(t('Priority must be a whole number between %s and %s.', AbstractNotification::MIN_PRIORITY, AbstractNotification::MAX_PRIORITY));
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

        $this->validateLinks($post);
        $this->validateAudience($post);

        if ($type === AbstractNotification::TYPE_POPUP) {
            $this->validatePopup($post);
        }
    }

    /**
     * Each locale's link must be complete for the type chosen: a picked page or file, or a typed value that validates.
     */
    protected function validateLinks(array $post): void
    {
        foreach ($this->locales as $locale) {
            $lang = $locale->getLanguage();
            $label = $locale->getLanguageText();
            $type = $this->postedLinkType($post, $lang);

            $message = match ($type) {
                LinkResolver::TYPE_PAGE => empty($post['linkCID'][$lang]) ? t('Choose a page for the link (%s).', $label) : null,
                LinkResolver::TYPE_FILE => empty($post['linkFID'][$lang]) ? t('Choose a file for the link (%s).', $label) : null,
                LinkResolver::TYPE_URL => LinkResolver::url($post['linkValue'][$lang] ?? '') === null ? t('Enter a valid web address for the link (%s), eg. https://example.com.', $label) : null,
                LinkResolver::TYPE_PHONE => LinkResolver::phone($post['linkValue'][$lang] ?? '') === null ? t('Enter a valid phone number for the link (%s), eg. +44 1234 567890.', $label) : null,
                LinkResolver::TYPE_EMAIL => LinkResolver::email($post['linkValue'][$lang] ?? '') === null ? t('Enter a valid email address for the link (%s).', $label) : null,
                default => null,
            };

            if ($message !== null) {
                $this->error->add($message);
            }
        }
    }

    /**
     * Audience::fromArray() quietly drops anything invalid, so what the admin typed is checked here and reported
     * rather than silently ignored.
     */
    protected function validateAudience(array $post): void
    {
        $key = trim((string) ($post['audienceParamKey'] ?? ''));
        $value = trim((string) ($post['audienceParamValue'] ?? ''));
        $referrer = trim((string) ($post['audienceReferrer'] ?? ''));

        if ($key !== '' && !Audience::isValidParamKey($key)) {
            $this->error->add(t('The URL parameter name can only use letters, numbers, dots, hyphens and underscores, and must start with a letter or underscore.'));
        }

        if ($key === '' && $value !== '') {
            $this->error->add(t('Enter the URL parameter name to go with its value.'));
        }

        if ($referrer !== '' && Audience::normaliseHost($referrer) === null) {
            $this->error->add(t('Enter the referring site as a domain name, eg. google.com.'));
        }
    }

    protected function postedAudience(array $post): Audience
    {
        return Audience::fromArray([
            'visitor' => $post['audienceVisitor'] ?? 'any',
            'devices' => $post['audienceDevices'] ?? [],
            'paramKey' => $post['audienceParamKey'] ?? '',
            'paramValue' => $post['audienceParamValue'] ?? '',
            'referrer' => $post['audienceReferrer'] ?? '',
        ]);
    }

    protected function postedLinkType(array $post, string $lang): string
    {
        $type = (string) ($post['linkType'][$lang] ?? '');

        return LinkResolver::isValidType($type) ? $type : LinkResolver::TYPE_NONE;
    }

    protected function fileById(mixed $id): ?FileEntity
    {
        $id = (int) $id;

        return $id > 0 ? $this->entityManager->find(FileEntity::class, $id) : null;
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
            ->setPageIds($entity->getDisplayScope() === 'specific' ? $this->postedPageIds($post) : [])
            ->setAudience($this->postedAudience($post));

        if ($entity instanceof BannerNotification) {
            $entity->setTransition($post['transition'] ?? 'fade')
                ->setInterval($post['interval'] ?? BannerNotification::DEFAULT_INTERVAL)
                ->setDismissible(isset($post['dismissible']));
        }

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

        }

        // Every posted image and link file in one query rather than one per locale.
        $files = [];
        $fileIds = array_values(array_unique(array_filter(array_map('intval', [
            ...(array) ($post['image'] ?? []),
            ...(array) ($post['linkFID'] ?? []),
        ]))));
        if ($fileIds !== []) {
            foreach ($this->entityManager->getRepository(FileEntity::class)->findBy(['fID' => $fileIds]) as $file) {
                $files[$file->getFileID()] = $file;
            }
        }

        foreach ($this->locales as $locale) {
            $lang = $locale->getLanguage();
            $content = $entity->getContent($lang) ?: (new NotificationContent())->setLanguage($lang);

            $linkType = $this->postedLinkType($post, $lang);

            // Only the field that belongs to the chosen type is kept; the others are cleared.
            $content->setName((string) ($post['name'][$lang] ?? ''))
                ->setContent((string) ($post['content'][$lang] ?? ''))
                ->setLinkType($linkType)
                ->setLinkText($linkType === LinkResolver::TYPE_NONE ? '' : (string) ($post['linkText'][$lang] ?? ''))
                ->setLinkCID($linkType === LinkResolver::TYPE_PAGE ? ($post['linkCID'][$lang] ?? null) : null)
                ->setLinkFile($linkType === LinkResolver::TYPE_FILE ? ($files[(int) ($post['linkFID'][$lang] ?? 0)] ?? null) : null)
                ->setLinkValue(in_array($linkType, LinkResolver::TEXT_TYPES, true) ? (string) ($post['linkValue'][$lang] ?? '') : null);

            if ($entity instanceof PopupNotification) {
                $content->setImage($files[(int) ($post['image'][$lang] ?? 0)] ?? null);
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
            'audienceVisitor' => (string) ($post['audienceVisitor'] ?? $entity?->getAudience()->visitor ?? 'any'),
            'audienceDevices' => $post !== null ? array_values(array_filter((array) ($post['audienceDevices'] ?? []), 'is_string')) : ($entity?->getAudience()->devices ?? []),
            'audienceParamKey' => (string) ($post['audienceParamKey'] ?? $entity?->getAudience()->paramKey ?? ''),
            'audienceParamValue' => (string) ($post['audienceParamValue'] ?? $entity?->getAudience()->paramValue ?? ''),
            'audienceReferrer' => (string) ($post['audienceReferrer'] ?? $entity?->getAudience()->referrer ?? ''),
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
                'linkType' => $post !== null ? $this->postedLinkType($post, $lang) : ($content?->getLinkType() ?? LinkResolver::TYPE_NONE),
                'linkValue' => (string) ($post['linkValue'][$lang] ?? $content?->getLinkValue() ?? ''),
                'linkFile' => $post !== null ? ($this->fileById($post['linkFID'][$lang] ?? 0) ?: false) : ($content ? $content->getLinkFile() : false),
                'image' => $post !== null ? ($this->fileById($post['image'][$lang] ?? 0) ?: false) : ($content ? $content->getImage() : false),
            ];
        }

        return $v;
    }

    /**
     * Impressions and clicks for the notifications on this page of the list. A failure here (eg. before the upgrade
     * that creates the table has run) leaves the columns at zero rather than taking the list down.
     *
     * @param string[] $ids
     *
     * @return array<string, array{impressions: int, clicks: int}>
     */
    protected function statTotals(array $ids): array
    {
        if (!Settings::get()->areStatsEnabled()) {
            return [];
        }

        try {
            return StatsStore::make()->totals($ids, self::STATS_DAYS);
        } catch (\Throwable) {
            return [];
        }
    }

    protected function statSummary(string $id): ?\NotificationManagerKit\Support\StatsSummary
    {
        if (!Settings::get()->areStatsEnabled()) {
            return null;
        }

        try {
            return StatsStore::make()->summary($id, self::STATS_DAYS);
        } catch (\Throwable) {
            return null;
        }
    }

    public function on_start()
    {
        parent::on_start();

        // getLocales() is a Doctrine collection; a plain array works with array_map() and the views alike.
        $this->locales = array_values($this->app->make('site')->getActiveSiteForEditing()->getLocales()->toArray());
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
        $this->set('statsEnabled', Settings::get()->areStatsEnabled());
        $this->set('statsDays', self::STATS_DAYS);
        $this->set('statTotals', $this->statTotals(array_map(fn($item) => $item->getID(), $result->getItems())));
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
        $this->set('statsEnabled', Settings::get()->areStatsEnabled());
        $this->set('statsDays', self::STATS_DAYS);
        $this->set('stats', $this->statSummary($entity->getID()));
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

    /**
     * Copies a notification (inactive, names prefixed "Copy of") and opens the copy for editing.
     */
    public function duplicate($id = false)
    {
        $id = (string) $id;
        $original = null;

        if (!$this->request->isPost() || !$this->token->validate('duplicate-notification-id-' . $id)) {
            $this->error->add($this->token->getErrorMessage());
        } else {
            $original = AbstractNotification::getByID($id) ?: null;

            if (!$original) {
                $this->error->add(t('Unable to find the specified notification.'));
            }
        }

        if ($this->error->has()) {
            $this->flash('error', implode(' ', $this->error->getList()));
            $this->buildRedirect('/dashboard/notification_manager/notifications/')->send();
            exit;
        }

        $copy = $original->duplicate();

        foreach ($copy->getData() as $content) {
            if ($content->getName() !== '') {
                $content->setName(mb_substr(t('Copy of %s', $content->getName()), 0, 255));
            }
            $this->entityManager->persist($content);
        }

        $this->entityManager->persist($copy);
        $this->entityManager->flush();
        Catalog::flush();

        $this->flash('success', t('Notification duplicated. The copy is inactive until you activate it.'));
        $this->buildRedirect('/dashboard/notification_manager/notifications/details/' . $copy->getID())->send();
        exit;
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
