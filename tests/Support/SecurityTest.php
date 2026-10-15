<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use NotificationManagerKit\Support\{Output, Sanitizer, SelectorValidator};

final class SecurityTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function payloadProvider(): array
    {
        return [
            'script tag' => ['<script>alert(1)</script>'],
            'attribute breakout' => ['"><script>alert(1)</script>'],
            'event handler' => ['<img src=x onerror=alert(1)>'],
            'single quote breakout' => ["' onmouseover='alert(1)"],
            'svg onload' => ['<svg onload=alert(1)>'],
        ];
    }

    #[DataProvider('payloadProvider')]
    public function testOutputEscapesEveryPayload(string $payload): void
    {
        $escaped = Output::e($payload);

        $this->assertStringNotContainsString('<', $escaped);
        $this->assertStringNotContainsString('"', $escaped);
        $this->assertStringNotContainsString("'", $escaped);
    }

    #[DataProvider('payloadProvider')]
    public function testSanitizerRemovesActiveContent(string $payload): void
    {
        $clean = Sanitizer::html('<p>Hello</p>' . $payload);

        $this->assertStringNotContainsStringIgnoringCase('<script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onload', $clean);
        $this->assertStringContainsString('Hello', $clean);
    }

    public function testSanitizerKeepsSafeFormatting(): void
    {
        $clean = Sanitizer::html('<p>Hi <strong>there</strong> <a href="https://example.com/x">link</a></p>');

        $this->assertStringContainsString('<strong>there</strong>', $clean);
        $this->assertStringContainsString('href="https://example.com/x"', $clean);
    }

    public function testSanitizerStripsUnsafeSchemes(): void
    {
        // htmLawed neutralises a disallowed scheme by prefixing it ("denied:javascript:..."), which browsers treat as a dead link.
        $this->assertDoesNotMatchRegularExpression('/href="\s*javascript:/i', Sanitizer::html('<a href="javascript:alert(1)">x</a>'));
        $this->assertDoesNotMatchRegularExpression('/href="\s*data:/i', Sanitizer::html('<a href="data:text/html;base64,AAAA">x</a>'));
    }

    public function testSanitizerHandlesEmptyInput(): void
    {
        $this->assertSame('', Sanitizer::html(null));
        $this->assertSame('', Sanitizer::html('   '));
    }

    public function testTextStripsMarkupAndTruncates(): void
    {
        $this->assertSame('alert(1)Hi', Sanitizer::text('<script>alert(1)</script>Hi'));
        $this->assertSame(5, mb_strlen(Sanitizer::text('abcdefghij', 5)));
    }

    public function testUrlAllowsSafeSchemesOnly(): void
    {
        $this->assertSame('https://example.com/a?b=1&amp;c=2', Output::url('https://example.com/a?b=1&c=2'));
        $this->assertSame('/relative/path', Output::url('/relative/path'));
        $this->assertSame('tel:+441234', Output::url('tel:+441234'));
        $this->assertSame('#', Output::url('javascript:alert(1)'));
        $this->assertSame('#', Output::url("java\tscript:alert(1)"));
        $this->assertSame('#', Output::url('JAVASCRIPT:alert(1)'));
        $this->assertSame('#', Output::url('data:text/html,<script>'));
        $this->assertSame('#', Output::url(''));
    }

    public function testJsonCannotCloseAScriptOrAttribute(): void
    {
        $json = Output::json(['a' => '</script><b onclick="x">&\'']);

        foreach (['<', '>', '"</', '&', "'"] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, str_replace('"a"', '', $json));
        }
        $this->assertSame(['a' => '</script><b onclick="x">&\''], json_decode($json, true));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function selectorProvider(): array
    {
        return [
            'id' => ['#signup', true],
            'class' => ['.open-popup', true],
            'tag and class' => ['a.btn', true],
            'attribute' => ['[data-popup]', true],
            'attribute value' => ['a[href="/contact"]', true],
            'descendant' => ['.nav .cta', true],
            'child' => ['.nav > .cta', true],
            'list' => ['#a, .b', true],
            'empty' => ['', false],
            'script injection' => ['<script>', false],
            'quote breakout' => ['a"]; alert(1); //', false],
            'unclosed attribute' => ['a[href', false],
            'pseudo with parens' => [':not(.x)', false],
            'trailing combinator' => ['.a >', false],
            'too long' => [str_repeat('a', 201), false],
            'angle in attribute' => ['a[href="<x>"]', false],
        ];
    }

    #[DataProvider('selectorProvider')]
    public function testSelectorValidation(string $selector, bool $valid): void
    {
        $this->assertSame($valid, SelectorValidator::isValid($selector));
        $this->assertSame($valid ? trim($selector) : null, SelectorValidator::sanitize($selector));
    }
}
