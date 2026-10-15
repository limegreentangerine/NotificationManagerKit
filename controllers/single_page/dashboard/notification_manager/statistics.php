<?php

namespace Concrete\Package\NotificationManagerKit\Controller\SinglePage\Dashboard\NotificationManager;

use Concrete\Core\Http\Response;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Page\Controller\DashboardPageController;
use NotificationManagerKit\Export\{PdfWriter, XlsxWriter};
use NotificationManagerKit\Support\{StatsChart, StatsExport, StatsFilter, StatsReport, StatsStore};
use NotificationManagerKit\Entity\{AbstractNotification, BannerNotification, PopupNotification, Settings};

class Statistics extends DashboardPageController
{
    protected function filter(): StatsFilter
    {
        return StatsFilter::fromRequest($this->request->query->all(), new \DateTimeImmutable());
    }

    /**
     * The notifications the filter's type selects, for the notification menu and the report.
     *
     * @return array<string, array{name: string, type: string}> id => name and type, in name order
     */
    protected function notifications(StatsFilter $filter): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('n', 'd')
            ->from(AbstractNotification::class, 'n')
            ->leftJoin('n.data', 'd');

        if ($filter->type === AbstractNotification::TYPE_BANNER) {
            $query->andWhere('n INSTANCE OF ' . BannerNotification::class);
        } elseif ($filter->type === AbstractNotification::TYPE_POPUP) {
            $query->andWhere('n INSTANCE OF ' . PopupNotification::class);
        }

        $notifications = [];
        foreach ($query->getQuery()->getResult() as $notification) {
            $notifications[$notification->getID()] = [
                'name' => $notification->getAnyName() ?: t('(Untitled)'),
                'type' => $notification->getType(),
            ];
        }

        uasort($notifications, fn(array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $notifications;
    }

    protected function report(StatsFilter $filter, array $notifications): StatsReport
    {
        // One notification asked for: report on just that one (and on nothing if the type filter excludes it).
        $selected = $filter->notificationId === ''
            ? $notifications
            : array_intersect_key($notifications, [$filter->notificationId => true]);

        try {
            $rows = StatsStore::make()->rows(array_keys($selected), $filter);
        } catch (\Throwable) {
            // eg. before the upgrade that creates the table has run: show empty figures rather than an error page.
            $rows = [];
        }

        return StatsReport::build($filter, $selected, $rows);
    }

    protected function xlsx(StatsReport $report, \DateTimeInterface $generated, string $siteName): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'nmstats');

        try {
            XlsxWriter::write($report, $path, $generated, $siteName);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    public function on_start()
    {
        parent::on_start();

        AssetList::getInstance()->register('css', 'notification-manager-kit/statistics', 'css/nm-stats.css', ['minify' => false, 'combine' => false], 'notification_manager_kit');
        $this->requireAsset('css', 'notification-manager-kit/statistics');
    }

    public function view()
    {
        $filter = $this->filter();
        $notifications = $this->notifications($filter);
        $report = $this->report($filter, $notifications);

        $daily = $report->totals->daily;
        $charts = [];
        foreach (['impressions' => t('Shown per day'), 'clicks' => t('Clicks per day'), 'dismissals' => t('Dismissed per day')] as $field => $title) {
            $charts[$field] = ['title' => $title, 'chart' => StatsChart::build(array_map(fn(array $day) => $day[$field], $daily))];
        }

        $this->set('filter', $filter);
        $this->set('report', $report);
        $this->set('notificationOptions', $notifications);
        $this->set('charts', $charts);
        $this->set('statsEnabled', Settings::get()->areStatsEnabled());
        $this->set('presets', StatsFilter::PRESETS);
    }

    /**
     * Downloads the report the page is showing: /statistics/export/xlsx|json|pdf with the same query string.
     */
    public function export($format = '')
    {
        $format = (string) $format;

        if (!StatsExport::isFormat($format)) {
            $this->flash('error', t('Choose Excel, JSON or PDF.'));

            return $this->buildRedirect('/dashboard/notification_manager/statistics')->send();
        }

        $filter = $this->filter();
        $report = $this->report($filter, $this->notifications($filter));
        $generated = new \DateTimeImmutable();
        $siteName = (string) $this->app->make('site')->getSite()->getSiteName();

        $body = match ($format) {
            'json' => StatsExport::json($report, $generated),
            'pdf' => PdfWriter::render($report, $generated, $siteName, fn(string $text, mixed ...$args) => t($text, ...$args)),
            default => $this->xlsx($report, $generated, $siteName),
        };

        $types = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'json' => 'application/json; charset=utf-8',
            'pdf' => 'application/pdf',
        ];

        return new Response($body, 200, [
            'Content-Type' => $types[$format],
            'Content-Disposition' => 'attachment; filename="' . StatsExport::filename($report, $format) . '"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
