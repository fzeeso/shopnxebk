<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL\Types;

use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\CategoryTranslation;

final class CategoryTranslationForLocale
{
    /** @param array{locale: string} $arguments */
    public function __invoke(Category $category, array $arguments): ?CategoryTranslation
    {
        $locale = strtolower(str_replace('-', '_', trim((string) $arguments['locale'])));

        if ($category->relationLoaded('translations')) {
            return $category->translations->first(
                fn (CategoryTranslation $translation): bool => strtolower(str_replace('-', '_', $translation->locale)) === $locale,
            );
        }

        return $category->translations()->whereRaw('LOWER(locale) = ?', [$locale])->first();
    }
}
