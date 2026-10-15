<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Support\{CatalogData, Eligibility, NotificationView, Schedule};

final class EligibilityTest extends TestCase
{
    private function view(string $id, string $type = 'banner', int $priority = 0, ?Schedule $schedule = null, string $scope = 'all', array $pageIds = [], int $version = 1, int $created = 1, array $audience = []): NotificationView
    {
        return new NotificationView($id, $type, $priority, $version, $created, $schedule ?? new Schedule(), $scope, $pageIds, 'Name', '<p>x</p>', '', '', null, [], $audience);
    }

    private function catalog(array $views, bool $banners = true, bool $popups = true): CatalogData
    {
        return new CatalogData($banners, $popups, false, [], $views);
    }

    private function ids(array $views): array
    {
        return array_map(fn($v) => $v->id, $views);
    }

    public function testManyBannersButOnlyOnePopup(): void
    {
        $result = Eligibility::select($this->catalog([
            $this->view('b1'),
            $this->view('b2'),
            $this->view('p1', 'popup', 1),
            $this->view('p2', 'popup', 5),
        ]), 10);

        $this->assertSame(['b1', 'b2'], $this->ids($result['banners']));
        $this->assertSame('p2', $result['popup']->id);
    }

    public function testBannersAreOrderedByPriorityThenNewestThenId(): void
    {
        $result = Eligibility::select($this->catalog([
            $this->view('a', priority: 1, version: 10),
            $this->view('b', priority: 5, version: 1),
            $this->view('c', priority: 1, version: 20),
        ]), 1);

        $this->assertSame(['b', 'c', 'a'], $this->ids($result['banners']));
    }

    public function testEqualPriorityAndVersionFallsBackToNewestCreated(): void
    {
        $result = Eligibility::select($this->catalog([
            $this->view('newer', 'popup', 3, created: 200),
            $this->view('older', 'popup', 3, created: 100),
        ]), 1);

        $this->assertSame('newer', $result['popup']->id);
    }

    public function testDisabledTypesAreSkipped(): void
    {
        $views = [$this->view('b'), $this->view('p', 'popup')];

        $noBanners = Eligibility::select($this->catalog($views, banners: false), 1);
        $this->assertSame([], $noBanners['banners']);
        $this->assertSame('p', $noBanners['popup']->id);

        $noPopups = Eligibility::select($this->catalog($views, popups: false), 1);
        $this->assertSame(['b'], $this->ids($noPopups['banners']));
        $this->assertNull($noPopups['popup']);
    }

    public function testScheduleIsEvaluatedAtRequestTime(): void
    {
        $catalog = $this->catalog([
            $this->view('live', schedule: new Schedule(new \DateTimeImmutable('2026-03-01'), new \DateTimeImmutable('2026-03-31'))),
            $this->view('future', schedule: new Schedule(new \DateTimeImmutable('2026-06-01'))),
            $this->view('expired', schedule: new Schedule(null, new \DateTimeImmutable('2026-01-01'))),
        ]);

        $this->assertSame(['live'], $this->ids(Eligibility::select($catalog, 1, new \DateTimeImmutable('2026-03-10'))['banners']));
        $this->assertSame(['future'], $this->ids(Eligibility::select($catalog, 1, new \DateTimeImmutable('2026-06-10'))['banners']));
    }

    public function testSpecificScopeMatchesExactPagesOnly(): void
    {
        $catalog = $this->catalog([
            $this->view('everywhere'),
            $this->view('some', scope: 'specific', pageIds: [5, 7]),
        ]);

        $this->assertSame(['everywhere', 'some'], $this->ids(Eligibility::select($catalog, 7)['banners']));
        $this->assertSame(['everywhere'], $this->ids(Eligibility::select($catalog, 8)['banners']));
    }

    public function testNothingEligibleReturnsEmpty(): void
    {
        $result = Eligibility::select($this->catalog([]), 1);

        $this->assertSame([], $result['banners']);
        $this->assertNull($result['popup']);
    }

    public function testPathExclusion(): void
    {
        $catalog = new CatalogData(true, true, false, ['/login', '/dashboard']);

        $this->assertTrue($catalog->isPathExcluded('/login'));
        $this->assertTrue($catalog->isPathExcluded('/Login/'));
        $this->assertTrue($catalog->isPathExcluded('/dashboard/system'));
        $this->assertFalse($catalog->isPathExcluded('/loginfo'));
        $this->assertFalse($catalog->isPathExcluded('/'));
    }

    public function testAnythingEnabledNeedsAFlagAndANotification(): void
    {
        $this->assertFalse((new CatalogData(true, true))->isAnythingEnabled());
        $this->assertFalse((new CatalogData(false, false, false, [], [$this->view('a')]))->isAnythingEnabled());
        $this->assertTrue((new CatalogData(true, false, false, [], [$this->view('a')]))->isAnythingEnabled());
    }

    public function testPopupCandidatesStopAtTheFirstOneWithoutAudienceRules(): void
    {
        $result = Eligibility::select($this->catalog([
            $this->view('top', 'popup', 9, audience: ['visitor' => 'new']),
            $this->view('mobile', 'popup', 7, audience: ['devices' => ['mobile']]),
            $this->view('everyone', 'popup', 5),
            $this->view('unreachable', 'popup', 1),
        ]), 1);

        $this->assertSame(['top', 'mobile', 'everyone'], $this->ids($result['popups']));
        $this->assertSame('top', $result['popup']->id);
    }

    public function testWithoutAudienceRulesThereIsOneCandidate(): void
    {
        $result = Eligibility::select($this->catalog([
            $this->view('a', 'popup', 2),
            $this->view('b', 'popup', 1),
        ]), 1);

        $this->assertSame(['a'], $this->ids($result['popups']));
    }

    public function testNoPopupsMeansNoCandidates(): void
    {
        $result = Eligibility::select($this->catalog([$this->view('b1')]), 1);

        $this->assertSame([], $result['popups']);
        $this->assertNull($result['popup']);
    }

    public function testBannersWithAudienceRulesAreStillSelected(): void
    {
        $result = Eligibility::select($this->catalog([
            $this->view('plain'),
            $this->view('targeted', audience: ['visitor' => 'returning']),
        ]), 1);

        $this->assertSame(['plain', 'targeted'], $this->ids($result['banners']));
    }
}
