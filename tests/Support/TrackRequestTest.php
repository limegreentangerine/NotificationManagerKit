<?php

declare(strict_types=1);

namespace NotificationManagerKit\Tests\Support;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use NotificationManagerKit\Support\TrackRequest;

final class TrackRequestTest extends TestCase
{
    private const ID_A = '441a281e-c719-11f1-bd37-cfbc4319b53f';
    private const ID_B = '60149570-c71b-11f1-bd37-cfbc4319b53f';

    private function body(array $events, string $lang = 'en'): string
    {
        return (string) json_encode(['lang' => $lang, 'events' => $events]);
    }

    public function testParsesEventsIntoCountsPerNotification(): void
    {
        $parsed = TrackRequest::parse($this->body([[self::ID_A, 'i'], [self::ID_A, 'c'], [self::ID_B, 'i'], [self::ID_B, 'd']]));

        $this->assertSame('en', $parsed['language']);
        $this->assertSame([
            self::ID_A => ['impressions' => 1, 'clicks' => 1, 'dismissals' => 0],
            self::ID_B => ['impressions' => 1, 'clicks' => 0, 'dismissals' => 1],
        ], $parsed['counts']);
    }

    public function testEachEventCountsOncePerNotificationPerRequest(): void
    {
        $parsed = TrackRequest::parse($this->body([[self::ID_A, 'i'], [self::ID_A, 'i'], [self::ID_A, 'c'], [self::ID_A, 'c'], [self::ID_A, 'd'], [self::ID_A, 'd']]));

        $this->assertSame(['impressions' => 1, 'clicks' => 1, 'dismissals' => 1], $parsed['counts'][self::ID_A]);
    }

    public function testIdsAreNormalisedToLowercase(): void
    {
        $parsed = TrackRequest::parse($this->body([[strtoupper(self::ID_A), 'i']]));

        $this->assertSame([self::ID_A], array_keys($parsed['counts']));
    }

    #[DataProvider('badEvents')]
    public function testInvalidEventsAreDropped(mixed $event): void
    {
        $this->assertNull(TrackRequest::parse($this->body([$event])));
    }

    public static function badEvents(): array
    {
        return [
            'unknown code' => [[self::ID_A, 'x']],
            'long code' => [[self::ID_A, 'impression']],
            'not a uuid' => [['abc', 'i']],
            'sql' => [["' OR 1=1 --", 'i']],
            'empty id' => [['', 'i']],
            'missing code' => [[self::ID_A]],
            'too many parts' => [[self::ID_A, 'i', 'x']],
            'not an array' => ['nope'],
            'numeric id' => [[123, 'i']],
            'array id' => [[[self::ID_A], 'i']],
        ];
    }

    public function testValidEventsSurviveInvalidNeighbours(): void
    {
        $parsed = TrackRequest::parse($this->body([['bad', 'i'], [self::ID_A, 'i'], [self::ID_A, 'zzz']]));

        $this->assertSame([self::ID_A => ['impressions' => 1, 'clicks' => 0, 'dismissals' => 0]], $parsed['counts']);
    }

    public function testNothingValidMeansNull(): void
    {
        $this->assertNull(TrackRequest::parse($this->body([])));
    }

    #[DataProvider('badBodies')]
    public function testMalformedBodies(string $body): void
    {
        $this->assertNull(TrackRequest::parse($body));
    }

    public static function badBodies(): array
    {
        return [
            'empty' => [''],
            'not json' => ['{nope'],
            'a string' => ['"hello"'],
            'a list' => ['[1,2,3]'],
            'no events' => ['{"lang":"en"}'],
            'events not a list' => ['{"lang":"en","events":"x"}'],
            'no lang' => [json_encode(['events' => [[self::ID_A, 'i']]])],
            'bad lang' => [json_encode(['lang' => '<script>', 'events' => [[self::ID_A, 'i']]])],
            'array lang' => [json_encode(['lang' => ['en'], 'events' => [[self::ID_A, 'i']]])],
            'too big' => [str_repeat(' ', TrackRequest::MAX_BYTES + 1) . '{}'],
        ];
    }

    public function testRegionalLanguageCodesAreAccepted(): void
    {
        $this->assertSame('pt_BR', TrackRequest::parse($this->body([[self::ID_A, 'i']], 'pt_BR'))['language']);
        $this->assertSame('en-GB', TrackRequest::parse($this->body([[self::ID_A, 'i']], 'en-GB'))['language']);
    }

    public function testOnlyTheFirstEventsOfAHugeRequestAreRead(): void
    {
        $events = [];
        for ($i = 0; $i < 70; $i++) {
            $events[] = [sprintf('00000000-0000-0000-0000-%012d', $i), 'i'];
        }

        $parsed = TrackRequest::parse($this->body($events));

        $this->assertCount(TrackRequest::MAX_EVENTS, $parsed['counts']);
    }

    public function testAnOversizedRequestIsRefusedOutright(): void
    {
        $events = [];
        for ($i = 0; $i < 200; $i++) {
            $events[] = [sprintf('00000000-0000-0000-0000-%012d', $i), 'i'];
        }

        $this->assertNull(TrackRequest::parse($this->body($events)));
    }

    #[DataProvider('bots')]
    public function testBotsAreRecognised(?string $userAgent, bool $isBot): void
    {
        $this->assertSame($isBot, TrackRequest::isBot($userAgent));
    }

    public static function bots(): array
    {
        return [
            'missing' => [null, true],
            'empty' => ['', true],
            'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
            'bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0)', true],
            'curl' => ['curl/8.4.0', true],
            'headless chrome' => ['Mozilla/5.0 HeadlessChrome/120.0', true],
            'lighthouse' => ['Mozilla/5.0 Chrome-Lighthouse', true],
            'python' => ['python-requests/2.31', true],
            'chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', false],
            'safari ios' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', false],
            'firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0', false],
        ];
    }

    #[DataProvider('origins')]
    public function testOriginMustMatchTheHostWhenPresent(?string $origin, string $host, bool $ok): void
    {
        $this->assertSame($ok, TrackRequest::isSameOrigin($origin, $host));
    }

    public static function origins(): array
    {
        return [
            'absent' => [null, 'example.com', true],
            'empty' => ['', 'example.com', true],
            'same' => ['https://example.com', 'example.com', true],
            'same with port' => ['https://example.com:8443', 'example.com:8443', true],
            'case' => ['https://EXAMPLE.com', 'example.com', true],
            'other site' => ['https://evil.example', 'example.com', false],
            'suffix trick' => ['https://example.com.evil.example', 'example.com', false],
            'null origin' => ['null', 'example.com', false],
            'garbage' => ['not a url', 'example.com', false],
            'other port' => ['https://example.com:9999', 'example.com:8443', false],
        ];
    }
}
