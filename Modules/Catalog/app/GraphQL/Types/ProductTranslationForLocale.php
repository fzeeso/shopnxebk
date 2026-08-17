<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Types;

use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;

final class ProductTranslationForLocale
{
    /** @param array{locale: string} $arguments */
    public function __invoke(Product $product, array $arguments): ?ProductTranslation
    {
        $locale = strtolower(str_replace('-', '_', trim((string) $arguments['locale'])));

        if ($product->relationLoaded('translations')) {
            return $product->translations->first(
                fn (ProductTranslation $translation): bool => strtolower(str_replace('-', '_', $translation->locale)) === $locale,
            );
        }

        return $product->translations()->whereRaw('LOWER(locale) = ?', [$locale])->first();
    }
}
