<?php

declare(strict_types=1);

namespace App\Support\Translations;

use JsonException;

final readonly class TranslationSnapshot
{
    /**
     * @param  array<string, string|null>  $sourceFields
     * @param  list<string>  $targetLocales
     * @param  list<string>  $requiredFields
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceLocale,
        public array $sourceFields,
        public array $targetLocales,
        public string $contentDescription,
        public array $requiredFields = [],
        public array $metadata = [],
    ) {}

    /** @throws JsonException */
    public function sourceHash(): string
    {
        return hash('sha256', json_encode([
            'locale' => self::localeKey($this->sourceLocale),
            'fields' => $this->sourceFields,
            'metadata' => $this->metadata,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @throws JsonException */
    public function requestHash(): string
    {
        $targets = array_values(array_unique(array_map(self::localeKey(...), $this->targetLocales)));
        sort($targets);

        return hash('sha256', json_encode([
            'source_hash' => $this->sourceHash(),
            'target_locales' => $targets,
        ], JSON_THROW_ON_ERROR));
    }

    private static function localeKey(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }
}
