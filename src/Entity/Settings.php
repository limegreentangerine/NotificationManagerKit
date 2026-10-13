<?php

namespace NotificationManagerKit\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityManagerInterface;
use ClassKit\Entity\Core\UpdatedGuidEntity;

/**
 * Package settings. There is only ever one row; nothing is stored in the package file config.
 *
 * @ORM\Entity
 * @ORM\Table(
 *      name="nmSettings",
 *      options={"comment": "Notification Manager settings"}
 * )
 */
class Settings extends UpdatedGuidEntity
{
    public const BUILT_IN_EXCLUDED_PATHS = ['/login', '/register', '/account/two_factor_auth'];

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default": false, "comment": "Show banners"})
     */
    protected $bannersEnabled = false;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default": false, "comment": "Show popups"})
     */
    protected $popupsEnabled = false;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default": false, "comment": "Skip the bundled stylesheet"})
     */
    protected $useCustomCss = false;

    /**
     * @ORM\Column(type="text", nullable=false, options={"comment": "Extra paths to never show notifications on, one per line"})
     */
    protected $excludedPaths = '';

    /**
     * @ORM\Column(type="string", length=32, nullable=false, options={"default": "en", "comment": "Locale used when a notification has no content for the visitor's locale"})
     */
    protected $defaultLanguage = 'en';

    /**
     * The saved settings, or an unsaved instance with the defaults (everything off) if none have been saved yet.
     * Never writes.
     */
    public static function get(): self
    {
        $settings = app(EntityManagerInterface::class)->getRepository(self::class)->findOneBy([]);

        return $settings instanceof self ? $settings : new self();
    }

    public function areBannersEnabled(): bool
    {
        return (bool) $this->bannersEnabled;
    }

    public function setBannersEnabled($value): self
    {
        $this->bannersEnabled = (bool) $value;

        return $this;
    }

    public function arePopupsEnabled(): bool
    {
        return (bool) $this->popupsEnabled;
    }

    public function setPopupsEnabled($value): self
    {
        $this->popupsEnabled = (bool) $value;

        return $this;
    }

    public function usesCustomCss(): bool
    {
        return (bool) $this->useCustomCss;
    }

    public function setUseCustomCss($value): self
    {
        $this->useCustomCss = (bool) $value;

        return $this;
    }

    public function getExcludedPathsText(): string
    {
        return (string) $this->excludedPaths;
    }

    /**
     * Keeps only well formed absolute paths, one per line.
     */
    public function setExcludedPathsText(?string $text): self
    {
        $this->excludedPaths = implode("\n", self::parsePaths((string) $text));

        return $this;
    }

    /**
     * Built-in exclusions plus the configured ones, normalised.
     *
     * @return string[]
     */
    public function getExcludedPaths(): array
    {
        return array_values(array_unique([...self::BUILT_IN_EXCLUDED_PATHS, ...self::parsePaths((string) $this->excludedPaths)]));
    }

    /**
     * @return string[]
     */
    public static function parsePaths(string $text): array
    {
        $paths = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = '/' . trim(trim($line), '/');

            if (preg_match('#^/[A-Za-z0-9._~\-/]*$#', $line) && $line !== '/') {
                $paths[] = strtolower($line);
            }
        }

        return array_values(array_unique($paths));
    }

    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage ?: 'en';
    }

    public function setDefaultLanguage(?string $language): self
    {
        $language = (string) $language;
        $this->defaultLanguage = preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?$/', $language) ? $language : 'en';

        return $this;
    }
}
