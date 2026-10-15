<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Entity;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Entity\{BannerNotification, NotificationContent, PopupNotification};

final class NotificationDuplicateTest extends TestCase
{
    private function raw(object $object, string $property): mixed
    {
        return (new \ReflectionProperty($object, $property))->getValue($object);
    }

    private function content(string $language, string $name): NotificationContent
    {
        $content = (new NotificationContent())
            ->setLanguage($language)
            ->setName($name)
            ->setLinkType('url')
            ->setLinkText('Read more')
            ->setLinkValue('https://example.com');

        // Set directly: setContent() runs Concrete's LinkAbstractor, which needs the application.
        (new \ReflectionProperty($content, 'content'))->setValue($content, '<p>Hello</p>');

        return $content;
    }

    public function testContentCopyIsANewRowWithTheSameValues(): void
    {
        $original = $this->content('en', 'Sale');
        $copy = $original->duplicate();

        $this->assertNotSame($original, $copy);
        $this->assertSame('en', $copy->getLanguage());
        $this->assertSame('Sale', $copy->getName());
        $this->assertSame('<p>Hello</p>', $this->raw($copy, 'content'));
        $this->assertSame('url', $copy->getLinkType());
        $this->assertSame('Read more', $copy->getLinkText());
        $this->assertSame('https://example.com', $copy->getLinkValue());
    }

    public function testBannerCopyKeepsEverythingButIsInactiveAndNew(): void
    {
        $start = new \DateTime('2026-12-01 09:00:00');
        $original = new BannerNotification();
        $original->setActive(true)
            ->setPriority(40)
            ->setStartDate($start)
            ->setEndDate(new \DateTime('2026-12-31 17:00:00'))
            ->setRepeatEvent('annually')
            ->setDisplayScope('specific')
            ->setPageIds([5, 9])
            ->addData($this->content('en', 'Sale'))
            ->addData($this->content('fr', 'Soldes'));
        $original->setTransition('ticker')->setInterval(9000)->setDismissible(true);

        $copy = $original->duplicate();

        $this->assertInstanceOf(BannerNotification::class, $copy);
        $this->assertNotSame($original, $copy);
        $this->assertFalse($copy->isActive());
        $this->assertTrue($original->isActive());
        $this->assertSame(40, $copy->getPriority());
        $this->assertEquals($start, $copy->getStartDate());
        $this->assertEquals(new \DateTime('2026-12-31 17:00:00'), $copy->getEndDate());
        $this->assertSame('annually', $copy->getRepeatEvent());
        $this->assertSame('specific', $copy->getDisplayScope());
        $this->assertSame([5, 9], $copy->getPageIds());
        $this->assertSame('ticker', $copy->getTransition());
        $this->assertSame(9000, $copy->getInterval());
        $this->assertTrue($copy->isDismissible());
    }

    public function testCopyOwnsItsContentAndPagesSoEditsDoNotLeakBack(): void
    {
        $original = (new BannerNotification())
            ->setDisplayScope('specific')
            ->setPageIds([5])
            ->addData($this->content('en', 'Sale'));

        $copy = $original->duplicate();
        $copy->getContent('en')->setName('Changed');
        $copy->setPageIds([7]);

        $this->assertSame('Sale', $original->getContent('en')->getName());
        $this->assertSame([5], $original->getPageIds());
        $this->assertCount(1, $copy->getData());
        $this->assertNotSame($original->getContent('en'), $copy->getContent('en'));
    }

    public function testDatesAreNotSharedBetweenOriginalAndCopy(): void
    {
        $original = (new BannerNotification())->setStartDate(new \DateTime('2026-12-01 09:00:00'));

        $copy = $original->duplicate();

        $this->assertNotSame($original->getStartDate(), $copy->getStartDate());
        $this->assertEquals($original->getStartDate(), $copy->getStartDate());
    }

    public function testPopupCopyKeepsEveryTriggerSetting(): void
    {
        $original = (new PopupNotification())
            ->setFrequency('weekly')
            ->setTriggerOnLoad(false)
            ->setTriggerDelayMs(2500)
            ->setTriggerOnScroll(true)
            ->setTriggerScrollValue(70)
            ->setTriggerScrollUnit('px')
            ->setTriggerOnScrollToElement(true)
            ->setTriggerScrollElementSelector('#footer')
            ->setTriggerOnClick(true)
            ->setTriggerClickSelector('.open-me')
            ->addData($this->content('en', 'Join'));

        $copy = $original->duplicate();

        $this->assertInstanceOf(PopupNotification::class, $copy);
        $this->assertFalse($copy->isActive());
        $this->assertSame('weekly', $copy->getFrequency());
        $this->assertFalse($copy->isTriggerOnLoad());
        $this->assertSame(2500, $copy->getTriggerDelayMs());
        $this->assertTrue($copy->isTriggerOnScroll());
        $this->assertSame(70, $copy->getTriggerScrollValue());
        $this->assertSame('px', $copy->getTriggerScrollUnit());
        $this->assertTrue($copy->isTriggerOnScrollToElement());
        $this->assertSame('#footer', $copy->getTriggerScrollElementSelector());
        $this->assertTrue($copy->isTriggerOnClick());
        $this->assertSame('.open-me', $copy->getTriggerClickSelector());
        $this->assertTrue($copy->hasUsableTrigger());
    }
}
