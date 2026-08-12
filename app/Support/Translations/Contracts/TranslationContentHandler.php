<?php

declare(strict_types=1);

namespace App\Support\Translations\Contracts;

use App\Models\TranslationRequest;
use App\Support\Translations\TranslationSelection;
use App\Support\Translations\TranslationSnapshot;
use Modules\Stores\Models\Store;

interface TranslationContentHandler
{
    public function contentType(): string;

    public function snapshot(
        Store $store,
        int $contentId,
        TranslationSelection $selection,
    ): ?TranslationSnapshot;

    /** @param array<string, array<string, string|null>> $translations */
    public function apply(
        TranslationRequest $request,
        TranslationSnapshot $snapshot,
        array $translations,
    ): void;
}
