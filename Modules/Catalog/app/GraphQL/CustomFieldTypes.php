<?php

declare(strict_types=1);

namespace Modules\Catalog\GraphQL;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\CustomFieldDefinition;
use Modules\Catalog\Models\CustomFieldDefinitionTranslation;
use Modules\Catalog\Models\CustomFieldOption;
use Modules\Catalog\Models\CustomFieldOptionTranslation;
use Modules\Catalog\Models\ProductCustomFieldValue;
use Modules\Catalog\Models\ProductCustomFieldValueTranslation;

final class CustomFieldTypes
{
    /** @param array{locale: string} $arguments */
    public function definitionTranslation(
        CustomFieldDefinition $definition,
        array $arguments,
    ): ?CustomFieldDefinitionTranslation {
        return $this->translation($definition->translations, (string) $arguments['locale']);
    }

    /** @param array{locale: string} $arguments */
    public function optionTranslation(
        CustomFieldOption $option,
        array $arguments,
    ): ?CustomFieldOptionTranslation {
        return $this->translation($option->translations, (string) $arguments['locale']);
    }

    /** @param array{locale: string} $arguments */
    public function valueTranslation(
        ProductCustomFieldValue $value,
        array $arguments,
    ): ?ProductCustomFieldValueTranslation {
        return $this->translation($value->translations, (string) $arguments['locale']);
    }

    private function translation(Collection $translations, string $locale): mixed
    {
        $key = strtolower(str_replace('-', '_', trim($locale)));

        return $translations->first(
            static fn ($translation): bool => strtolower(
                str_replace('-', '_', (string) $translation->locale),
            ) === $key,
        );
    }
}
