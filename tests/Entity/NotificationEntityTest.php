<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use NotificationManagerKit\Entity\{AbstractNotification, BannerNotification, NotificationContent, NotificationPage, PopupNotification, Settings};

final class NotificationEntityTest extends TestCase
{
    private function metadata(string $class): ClassMetadataInfo
    {
        $config = new \Doctrine\ORM\Configuration();
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('NmkTestProxies');
        $config->setMetadataDriverImpl(new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
            new \Doctrine\Common\Annotations\AnnotationReader(),
            [dirname(__DIR__, 2) . '/src/Entity'],
        ));

        $em = new \Doctrine\ORM\EntityManager(
            \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            $config,
        );

        return $em->getClassMetadata($class);
    }

    public function testDiscriminatorMapsBothTypesIntoOneTable(): void
    {
        $meta = $this->metadata(AbstractNotification::class);

        $this->assertSame(ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE, $meta->inheritanceType);
        $this->assertSame('type', $meta->discriminatorColumn['name']);
        $this->assertSame([
            'banner' => BannerNotification::class,
            'popup' => PopupNotification::class,
        ], $meta->discriminatorMap);
        $this->assertSame('nmNotifications', $meta->getTableName());
        $this->assertSame('nmNotifications', $this->metadata(PopupNotification::class)->getTableName());
    }

    public function testTypeSpecificColumnsAreNullableForSingleTableInheritance(): void
    {
        foreach ([BannerNotification::class, PopupNotification::class] as $class) {
            $meta = $this->metadata($class);
            $shared = array_keys($this->metadata(AbstractNotification::class)->fieldMappings);

            foreach ($meta->fieldMappings as $name => $mapping) {
                if (!in_array($name, $shared, true)) {
                    $this->assertTrue($mapping['nullable'] ?? false, "$class::$name must be nullable");
                }
            }
        }
    }

    /**
     * Doctrine does not quote identifiers, so a column called `interval` (reserved in MySQL) made every banner insert fail
     * with a syntax error that no unit test noticed. Check every mapped table and column name against MySQL's keywords.
     */
    public function testNoMappedTableOrColumnIsAMysqlReservedWord(): void
    {
        $keywords = new \Doctrine\DBAL\Platforms\Keywords\MySQL80Keywords();
        $reserved = [];

        foreach ([AbstractNotification::class, BannerNotification::class, PopupNotification::class, NotificationContent::class, NotificationPage::class, Settings::class] as $class) {
            $meta = $this->metadata($class);

            if ($keywords->isKeyword($meta->getTableName())) {
                $reserved[] = $meta->getTableName();
            }

            foreach ($meta->getColumnNames() as $column) {
                if ($keywords->isKeyword($column)) {
                    $reserved[] = $meta->getTableName() . '.' . $column;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($reserved)), 'reserved words must be mapped to a different column name');
    }

    public function testSettingsAndContentTablesAreMapped(): void
    {
        $this->assertSame('nmSettings', $this->metadata(Settings::class)->getTableName());
        $this->assertSame('nmNotificationPages', $this->metadata(NotificationPage::class)->getTableName());
    }

    public function testCreateReturnsTheRightSubclass(): void
    {
        $this->assertInstanceOf(BannerNotification::class, AbstractNotification::create('banner'));
        $this->assertInstanceOf(PopupNotification::class, AbstractNotification::create('popup'));
        $this->assertNull(AbstractNotification::create('toast'));
        $this->assertSame('banner', (new BannerNotification())->getType());
        $this->assertSame('popup', (new PopupNotification())->getType());
    }

    public function testRepeatEventAndScopeAreAllowListed(): void
    {
        $n = (new BannerNotification())->setRepeatEvent('hourly')->setDisplayScope('<script>');

        $this->assertSame('none', $n->getRepeatEvent());
        $this->assertSame('all', $n->getDisplayScope());
        $this->assertSame('weekdays', $n->setRepeatEvent('weekdays')->getRepeatEvent());
    }

    public function testRecurrenceAppliesToBothTypes(): void
    {
        $monday = new \DateTimeImmutable('2026-03-09 10:00');
        $tuesday = new \DateTimeImmutable('2026-03-10 10:00');

        foreach ([new BannerNotification(), new PopupNotification()] as $n) {
            $n->setStartDate(new \DateTimeImmutable('2026-01-01'))->setRepeatEvent('weekly_monday');

            $this->assertTrue($n->isWithinSchedule($monday), $n->getType());
            $this->assertFalse($n->isWithinSchedule($tuesday), $n->getType());
        }
    }

    public function testPageTargeting(): void
    {
        $n = (new BannerNotification())->setDisplayScope('specific')->setPageIds([3, 3, 0, -1, 9]);

        $this->assertSame([3, 9], $n->getPageIds());
        $this->assertTrue($n->isVisibleOnPageId(9));
        $this->assertFalse($n->isVisibleOnPageId(4));

        $n->setPageIds([4]);
        $this->assertSame([4], $n->getPageIds());
        $this->assertTrue((new BannerNotification())->isVisibleOnPageId(123));
    }

    public function testPriorityValidation(): void
    {
        foreach ([null, '', '0', '-1000', '1000', ' 5 ', 7, -1000, 1000] as $ok) {
            $this->assertTrue(AbstractNotification::isValidPriority($ok), var_export($ok, true));
        }

        foreach (['1001', '-1001', 1001, -1001, '99999', '1.5', 'abc', '1e3', '<script>', [], 1.5] as $bad) {
            $this->assertFalse(AbstractNotification::isValidPriority($bad), var_export($bad, true));
        }
    }

    public function testStoredPriorityIsAlwaysClamped(): void
    {
        $this->assertSame(1000, (new BannerNotification())->setPriority(5000)->getPriority());
        $this->assertSame(-1000, (new PopupNotification())->setPriority(-5000)->getPriority());
    }

    public function testBannerOptionsAreClamped(): void
    {
        $b = (new BannerNotification())->setTransition('spin')->setInterval(5)->setDismissible('1');

        $this->assertSame('fade', $b->getTransition());
        $this->assertSame(2000, $b->getInterval());
        $this->assertSame(60000, $b->setInterval(999999)->getInterval());
        $this->assertTrue($b->isDismissible());
    }

    public function testPopupSelectorsAreValidatedOnTheWayIn(): void
    {
        $p = (new PopupNotification())
            ->setTriggerOnLoad(false)
            ->setTriggerOnClick(true)
            ->setTriggerClickSelector('a"]; alert(1); //');

        $this->assertSame('', $p->getTriggerClickSelector());
        $this->assertFalse($p->hasUsableTrigger(), 'a click trigger with no valid selector cannot fire');

        $p->setTriggerClickSelector('.open-popup');
        $this->assertSame('.open-popup', $p->getTriggerClickSelector());
        $this->assertTrue($p->hasUsableTrigger());
    }

    public function testPopupOptionsAreAllowListedAndClamped(): void
    {
        $p = (new PopupNotification())->setFrequency('always')->setTriggerScrollUnit('em')->setTriggerDelayMs(-5);

        $this->assertSame('every_time_until_close', $p->getFrequency());
        $this->assertSame('percent', $p->getTriggerScrollUnit());
        $this->assertSame(0, $p->getTriggerDelayMs());
        $this->assertSame(100, $p->setTriggerScrollValue(500)->getTriggerScrollValue());
    }

    public function testSettingsDefaultsToEverythingOff(): void
    {
        $s = new Settings();

        $this->assertFalse($s->areBannersEnabled());
        $this->assertFalse($s->arePopupsEnabled());
        $this->assertFalse($s->usesCustomCss());
        $this->assertSame('en', $s->getDefaultLanguage());
        $this->assertSame(Settings::BUILT_IN_EXCLUDED_PATHS, $s->getExcludedPaths());
    }

    public function testSettingsExcludedPathsAreNormalisedAndFiltered(): void
    {
        $s = (new Settings())->setExcludedPathsText("  /Checkout/ \n\ncart\n/<script>\n/a b\n/ok-path_1/sub");

        $this->assertSame("/checkout\n/cart\n/ok-path_1/sub", $s->getExcludedPathsText());
        $this->assertContains('/login', $s->getExcludedPaths());
        $this->assertContains('/checkout', $s->getExcludedPaths());
    }

    public function testSettingsLanguageIsValidated(): void
    {
        $this->assertSame('fr', (new Settings())->setDefaultLanguage('fr')->getDefaultLanguage());
        $this->assertSame('pt_BR', (new Settings())->setDefaultLanguage('pt_BR')->getDefaultLanguage());
        $this->assertSame('en', (new Settings())->setDefaultLanguage('<x>')->getDefaultLanguage());
    }
}
