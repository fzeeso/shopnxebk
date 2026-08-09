<?php

declare(strict_types=1);

namespace App\Support\Translations;

interface TranslationProvider
{
    /**
     * @param  array<string, string|null>  $sourceFields
     * @param  list<string>  $targetLocales
     * @param  list<string>  $requiredFields
     * @return array<string, array<string, string|null>>
     */
    public function translateFields(
        array $sourceFields,
        string $sourceLocale,
        array $targetLocales,
        string $contentType,
        array $requiredFields = [],
    ): array;
}
