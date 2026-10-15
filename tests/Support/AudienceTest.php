<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use NotificationManagerKit\Support\Audience;
use PHPUnit\Framework\Attributes\DataProvider;

final class AudienceTest extends TestCase
{
    public function testNoRulesIsEmpty(): void
    {
        $this->assertTrue(Audience::none()->isEmpty());
        $this->assertTrue(Audience::fromArray(null)->isEmpty());
        $this->assertTrue(Audience::fromArray([])->isEmpty());
        $this->assertSame([], Audience::none()->toArray());
        $this->assertNull(Audience::none()->toJson());
    }

    public function testEveryConditionRoundTripsThroughJson(): void
    {
        $audience = Audience::fromArray([
            'visitor' => 'returning',
            'devices' => ['mobile', 'tablet'],
            'paramKey' => 'utm_campaign',
            'paramValue' => 'spring',
            'referrer' => 'google.com',
        ]);

        $this->assertFalse($audience->isEmpty());
        $this->assertEquals($audience, Audience::fromJson($audience->toJson()));
        $this->assertSame([
            'visitor' => 'returning',
            'devices' => ['mobile', 'tablet'],
            'param' => ['key' => 'utm_campaign', 'value' => 'spring'],
            'referrer' => 'google.com',
        ], $audience->toArray());
    }

    public function testOnlySetConditionsAreEmitted(): void
    {
        $this->assertSame(['visitor' => 'new'], Audience::fromArray(['visitor' => 'new'])->toArray());
        $this->assertSame(['devices' => ['desktop']], Audience::fromArray(['devices' => ['desktop']])->toArray());
        $this->assertSame(['param' => ['key' => 'ref', 'value' => '']], Audience::fromArray(['paramKey' => 'ref'])->toArray());
    }

    public function testUnknownVisitorTypesMeanAnyone(): void
    {
        $this->assertTrue(Audience::fromArray(['visitor' => 'vip'])->isEmpty());
        $this->assertTrue(Audience::fromArray(['visitor' => 'any'])->isEmpty());
    }

    public function testDevicesAreFilteredAndDeduplicated(): void
    {
        $this->assertSame(['mobile'], Audience::fromArray(['devices' => ['mobile', 'tv', 'mobile', 5]])->toArray()['devices']);
    }

    public function testAllDevicesMeansNoDeviceRule(): void
    {
        $this->assertTrue(Audience::fromArray(['devices' => ['desktop', 'tablet', 'mobile']])->isEmpty());
    }

    public function testAParameterValueNeedsAKey(): void
    {
        $this->assertTrue(Audience::fromArray(['paramValue' => 'spring'])->isEmpty());
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidParameterKeysAreDropped(string $key): void
    {
        $this->assertFalse(Audience::isValidParamKey($key));
        $this->assertTrue(Audience::fromArray(['paramKey' => $key, 'paramValue' => 'x'])->isEmpty());
    }

    public static function invalidKeys(): array
    {
        return [
            'empty' => [''],
            'space' => ['utm campaign'],
            'brackets' => ['a[b]'],
            'starts with digit' => ['1abc'],
            'quote' => ['a"b'],
            'angle' => ['<script>'],
            'too long' => [str_repeat('a', 65)],
        ];
    }

    #[DataProvider('validKeys')]
    public function testValidParameterKeys(string $key): void
    {
        $this->assertTrue(Audience::isValidParamKey($key));
    }

    public static function validKeys(): array
    {
        return [['utm_campaign'], ['ref'], ['a.b-c'], ['_x'], ['Campaign']];
    }

    public function testParameterValuesAreTrimmedAndLimited(): void
    {
        $audience = Audience::fromArray(['paramKey' => 'ref', 'paramValue' => '  spring  ']);
        $this->assertSame('spring', $audience->toArray()['param']['value']);

        $long = Audience::fromArray(['paramKey' => 'ref', 'paramValue' => str_repeat('x', 500)]);
        $this->assertSame(100, mb_strlen($long->toArray()['param']['value']));
    }

    #[DataProvider('hosts')]
    public function testHostNormalisation(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Audience::normaliseHost($input));
    }

    public static function hosts(): array
    {
        return [
            'plain' => ['google.com', 'google.com'],
            'uppercase' => ['Google.COM', 'google.com'],
            'www stripped' => ['www.google.com', 'google.com'],
            'url' => ['https://www.google.com/search?q=x', 'google.com'],
            'subdomain kept' => ['news.ycombinator.com', 'news.ycombinator.com'],
            'surrounding space' => ['  bing.com ', 'bing.com'],
            'blank' => ['', null],
            'no dot' => ['localhost', null],
            'spaces inside' => ['not a host', null],
            'script' => ['javascript:alert(1)', null],
            'angle' => ['<b>.com', null],
            'underscore' => ['a_b.com', null],
            'trailing dot label' => ['example..com', null],
        ];
    }

    public function testReferrerIsNormalisedOnTheWayIn(): void
    {
        $this->assertSame('google.com', Audience::fromArray(['referrer' => 'https://www.Google.com/'])->toArray()['referrer']);
        $this->assertTrue(Audience::fromArray(['referrer' => 'nonsense'])->isEmpty());
    }

    public function testBrokenJsonMeansNoRules(): void
    {
        $this->assertTrue(Audience::fromJson('{not json')->isEmpty());
        $this->assertTrue(Audience::fromJson('"a string"')->isEmpty());
        $this->assertTrue(Audience::fromJson(null)->isEmpty());
        $this->assertTrue(Audience::fromJson('')->isEmpty());
    }

    public function testFromJsonRenormalisesStoredValues(): void
    {
        $audience = Audience::fromJson('{"visitor":"bogus","devices":["tv"],"param":{"key":"<x>","value":"v"},"referrer":"bad host"}');

        $this->assertTrue($audience->isEmpty());
    }
}
