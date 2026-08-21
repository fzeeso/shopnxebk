<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Types;

use Modules\Catalog\Models\ProductType;
use Modules\Catalog\Models\ProductTypeTranslation;

final class ProductTypeTranslationForLocale
{
    /** @param array{locale: string} $arguments */
    public function __invoke(ProductType $productType, array $arguments): ?ProductTypeTranslation
    {
        $locale = strtolower(str_replace('-', '_', trim((string) $arguments['locale'])));

        if ($productType->relationLoaded('translations')) {
            return $productType->translations->first(
                fn (ProductTypeTranslation $translation): bool => strtolower(str_replace('-', '_', $translation->locale)) === $locale,
            );
        }

        return $productType->translations()->whereRaw('LOWER(locale) = ?', [$locale])->first();
    }
}
