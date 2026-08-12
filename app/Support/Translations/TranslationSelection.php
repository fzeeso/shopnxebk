<?php

declare(strict_types=1);

namespace App\Support\Translations;

final readonly class TranslationSelection
{
    /** @param list<string>|null $targetLocales */
    public function __construct(
        public ?string $expectedSourceLocale = null,
        public ?array $targetLocales = null,
        public bool $missingOnly = false,
    ) {}
}
