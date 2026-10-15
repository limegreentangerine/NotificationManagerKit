<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Entity;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Entity\NotificationContent;

final class NotificationContentLinkTest extends TestCase
{
    public function testDefaultsToNoLink(): void
    {
        $content = new NotificationContent();

        $this->assertSame('none', $content->getLinkType());
        $this->assertTrue($content->hasValidLink());
    }

    public function testRowsFromBeforeLinkTypesExistedAreReadAsPageLinks(): void
    {
        $content = (new NotificationContent())->setLinkCID(12);

        $this->assertSame('page', $content->getLinkType());
        $this->assertTrue($content->hasValidLink());
    }

    public function testUnknownTypesBecomeNone(): void
    {
        $this->assertSame('none', (new NotificationContent())->setLinkType('javascript')->getLinkType());
        $this->assertSame('none', (new NotificationContent())->setLinkType('')->getLinkType());
    }

    public function testEachTypeIsStoredAndReadBack(): void
    {
        foreach (['none', 'page', 'file', 'url', 'phone', 'email'] as $type) {
            $this->assertSame($type, (new NotificationContent())->setLinkType($type)->getLinkType());
        }
    }

    public function testPageAndFileLinksNeedSomethingPicked(): void
    {
        $this->assertFalse((new NotificationContent())->setLinkType('page')->hasValidLink());
        $this->assertTrue((new NotificationContent())->setLinkType('page')->setLinkCID(5)->hasValidLink());
        $this->assertFalse((new NotificationContent())->setLinkType('file')->hasValidLink());
    }

    public function testTypedLinksMustValidate(): void
    {
        $url = (new NotificationContent())->setLinkType('url');
        $this->assertFalse($url->setLinkValue('')->hasValidLink());
        $this->assertFalse($url->setLinkValue('javascript:alert(1)')->hasValidLink());
        $this->assertTrue($url->setLinkValue('https://example.com')->hasValidLink());

        $phone = (new NotificationContent())->setLinkType('phone');
        $this->assertFalse($phone->setLinkValue('call us')->hasValidLink());
        $this->assertTrue($phone->setLinkValue('+44 1234 567890')->hasValidLink());

        $email = (new NotificationContent())->setLinkType('email');
        $this->assertFalse($email->setLinkValue('nope')->hasValidLink());
        $this->assertTrue($email->setLinkValue('hello@example.com')->hasValidLink());
    }

    public function testLinkValueIsTrimmedBlankClearsItAndLengthIsCapped(): void
    {
        $content = new NotificationContent();

        $this->assertSame('a@example.com', $content->setLinkValue('  a@example.com ')->getLinkValue());
        $this->assertSame('', $content->setLinkValue('   ')->getLinkValue());
        $this->assertSame(2000, mb_strlen($content->setLinkValue(str_repeat('a', 5000))->getLinkValue()));
    }

    public function testLinkTextIsPlainText(): void
    {
        $this->assertSame('Call us', (new NotificationContent())->setLinkText('<b>Call</b> us')->getLinkText());
    }
}
