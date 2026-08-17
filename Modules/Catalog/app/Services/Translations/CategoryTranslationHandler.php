<?php

declare(strict_types=1);

namespace Modules\Catalog\Services\Translations;

final readonly class CategoryTranslationHandler extends CatalogEntityTranslationHandler
{
    public function contentType(): string
    {
        return 'category';
    }

    protected function entityTable(): string
    {
        return 'categories';
    }

    protected function translationTable(): string
    {
        return 'category_translations';
    }

    protected function foreignKey(): string
    {
        return 'category_id';
    }

    protected function fields(): array
    {
        return ['title', 'description', 'seo_title', 'seo_description', 'page_title', 'search_keywords'];
    }

    protected function titleField(): string
    {
        return 'title';
    }

    protected function contentDescription(): string
    {
        return 'ecommerce category navigation and SEO metadata';
    }
}
