<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use NotificationManagerKit\Support\LinkResolver;

final class LinkResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function urlProvider(): array
    {
        return [
            'https' => ['https://example.com/a?b=1', 'https://example.com/a?b=1'],
            'http' => ['http://example.com', 'http://example.com'],
            'bare domain gets https' => ['example.com/pricing', 'https://example.com/pricing'],
            'bare domain with www and port' => ['www.example.co.uk:8080/x', 'https://www.example.co.uk:8080/x'],
            'surrounding space' => ['  https://example.com  ', 'https://example.com'],
            'javascript' => ['javascript:alert(1)', null],
            'javascript mixed case' => ['JaVaScRiPt:alert(1)', null],
            'javascript with tab' => ["java\tscript:alert(1)", null],
            'data' => ['data:text/html,<script>alert(1)</script>', null],
            'ftp' => ['ftp://example.com/file', null],
            'mailto is not a url' => ['mailto:a@example.com', null],
            'relative path' => ['/contact', null],
            'no host' => ['https://', null],
            'angle brackets' => ['https://example.com/<script>', null],
            'quote breakout' => ['https://example.com/" onclick="x', null],
            'backslash' => ['https://example.com\\@evil.com', null],
            'empty' => ['', null],
            'single word' => ['localhost', null],
        ];
    }

    #[DataProvider('urlProvider')]
    public function testUrl(string $input, ?string $expected): void
    {
        $this->assertSame($expected, LinkResolver::url($input));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function phoneProvider(): array
    {
        return [
            'international' => ['+44 1234 567890', 'tel:+441234567890'],
            'uk national' => ['01234 567890', 'tel:01234567890'],
            'brackets and dashes' => ['(01234) 567-890', 'tel:01234567890'],
            'dots' => ['01234.567.890', 'tel:01234567890'],
            'too short' => ['123', null],
            'too long' => ['1234567890123456', null],
            'letters' => ['call me', null],
            'injection' => ['123456;javascript:alert(1)', null],
            'plus in the middle' => ['0123+456789', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('phoneProvider')]
    public function testPhone(string $input, ?string $expected): void
    {
        $this->assertSame($expected, LinkResolver::phone($input));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function emailProvider(): array
    {
        return [
            'plain' => ['hello@example.com', 'mailto:hello@example.com'],
            'plus and dots' => ['first.last+news@sub.example.co.uk', 'mailto:first.last+news@sub.example.co.uk'],
            'surrounding space' => [' hello@example.com ', 'mailto:hello@example.com'],
            'no domain' => ['hello@', null],
            'no tld' => ['hello@localhost', null],
            'display name' => ['Hello <hello@example.com>', null],
            'header injection' => ['hello@example.com?cc=boss@example.com', null],
            'newline injection' => ["hello@example.com\nBcc: x@example.com", null],
            'quote' => ['"a b"@example.com', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('emailProvider')]
    public function testEmail(string $input, ?string $expected): void
    {
        $this->assertSame($expected, LinkResolver::email($input));
    }

    public function testHrefDispatchesByType(): void
    {
        $this->assertSame('https://example.com', LinkResolver::href('url', 'example.com'));
        $this->assertSame('tel:+441234567890', LinkResolver::href('phone', '+44 1234 567890'));
        $this->assertSame('mailto:a@example.com', LinkResolver::href('email', 'a@example.com'));
        $this->assertNull(LinkResolver::href('page', 'https://example.com'), 'page and file links are not free text');
        $this->assertNull(LinkResolver::href('none', 'https://example.com'));
        $this->assertNull(LinkResolver::href('url', 'javascript:alert(1)'));
    }

    public function testDefaultText(): void
    {
        $this->assertSame('+44 1234 567890', LinkResolver::defaultText('phone', ' +44 1234 567890 '));
        $this->assertSame('a@example.com', LinkResolver::defaultText('email', 'a@example.com'));
        $this->assertSame('example.com', LinkResolver::defaultText('url', 'https://example.com/path'));
        $this->assertSame('', LinkResolver::defaultText('page', 'x'));
    }

    public function testTypeValidation(): void
    {
        foreach (['none', 'page', 'file', 'url', 'phone', 'email'] as $type) {
            $this->assertTrue(LinkResolver::isValidType($type));
        }

        $this->assertFalse(LinkResolver::isValidType('javascript'));
        $this->assertFalse(LinkResolver::isValidType(''));
    }
}
