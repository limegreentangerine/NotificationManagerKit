<?php

namespace NotificationManagerKit\Support;

/**
 * Who a notification is for, beyond which pages it is on. Every condition that is set must match (AND);
 * a condition left blank is not checked. The rules are evaluated in the browser (js/nm-audience.js), so pages
 * stay cacheable: this class only validates, normalises and serialises them. No database, no Concrete.
 */
final readonly class Audience
{
    private const MAX_VALUE_LENGTH = 100;
    public const VISITORS = [
        'any' => 'Anyone',
        'new' => 'New visitors only',
        'returning' => 'Returning visitors only',
    ];

    public const DEVICES = [
        'mobile' => 'Mobile',
        'tablet' => 'Tablet',
        'desktop' => 'Desktop',
    ];

    /**
     * @param string[] $devices Empty means every device
     */
    private function __construct(
        public string $visitor = 'any',
        public array $devices = [],
        public string $paramKey = '',
        public string $paramValue = '',
        public string $referrer = '',
    ) {}

    public static function none(): self
    {
        return new self();
    }

    /**
     * Builds from untrusted input (a form post, or decoded JSON). Anything unrecognised is dropped, never trusted.
     *
     * @param array<string, mixed>|null $data visitor, devices, paramKey, paramValue, referrer
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $visitor = is_string($data['visitor'] ?? null) && isset(self::VISITORS[$data['visitor']]) ? $data['visitor'] : 'any';

        $devices = array_values(array_unique(array_filter(
            (array) ($data['devices'] ?? []),
            fn($device) => is_string($device) && isset(self::DEVICES[$device]),
        )));
        // Selecting every device is the same as selecting none.
        if (count($devices) === count(self::DEVICES)) {
            $devices = [];
        }
        // Keep a stable order whatever order the form posted.
        $devices = array_values(array_intersect(array_keys(self::DEVICES), $devices));

        $key = is_string($data['paramKey'] ?? null) ? trim($data['paramKey']) : '';
        $key = self::isValidParamKey($key) ? $key : '';
        $value = $key !== '' && is_string($data['paramValue'] ?? null) ? mb_substr(trim($data['paramValue']), 0, self::MAX_VALUE_LENGTH) : '';

        $referrer = is_string($data['referrer'] ?? null) ? (self::normaliseHost($data['referrer']) ?? '') : '';

        return new self($visitor, $devices, $key, $value, $referrer);
    }

    public static function fromJson(?string $json): self
    {
        if ($json === null || $json === '') {
            return self::none();
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            return self::none();
        }

        $param = is_array($data['param'] ?? null) ? $data['param'] : [];

        return self::fromArray([
            'visitor' => $data['visitor'] ?? null,
            'devices' => $data['devices'] ?? [],
            'paramKey' => $param['key'] ?? '',
            'paramValue' => $param['value'] ?? '',
            'referrer' => $data['referrer'] ?? '',
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    /**
     * Only the conditions that are set, in the shape js/nm-audience.js reads.
     *
     * @return array{visitor?: string, devices?: string[], param?: array{key: string, value: string}, referrer?: string}
     */
    public function toArray(): array
    {
        $rules = [];

        if ($this->visitor !== 'any') {
            $rules['visitor'] = $this->visitor;
        }

        if ($this->devices !== []) {
            $rules['devices'] = $this->devices;
        }

        if ($this->paramKey !== '') {
            $rules['param'] = ['key' => $this->paramKey, 'value' => $this->paramValue];
        }

        if ($this->referrer !== '') {
            $rules['referrer'] = $this->referrer;
        }

        return $rules;
    }

    public function toJson(): ?string
    {
        return $this->isEmpty() ? null : (string) json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * A query string key: letters, digits, underscore, dot and hyphen, starting with a letter or underscore.
     */
    public static function isValidParamKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,63}$/', $key);
    }

    /**
     * A bare lowercase hostname without a leading "www.", from either a hostname or a full URL; null if it isn't one.
     * Matching in the browser treats it as the host itself or any subdomain of it.
     */
    public static function normaliseHost(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            $host = parse_url($input, PHP_URL_HOST);
            $input = is_string($host) ? $host : '';
        }

        $host = strtolower(rtrim($input, '.'));
        $host = preg_replace('/^www\./', '', $host) ?? '';

        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

        if (strlen($host) > 253 || !preg_match('/^' . $label . '(?:\.' . $label . ')+$/', $host)) {
            return null;
        }

        return $host;
    }
}
