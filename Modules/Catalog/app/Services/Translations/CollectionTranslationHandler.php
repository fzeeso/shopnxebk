<?php

declare(strict_types=1);

namespace Modules\Catalog\Services\Translations;

final readonly class CollectionTranslationHandler extends CatalogEntityTranslationHandler
{
    public function contentType(): string
    {
        return 'collection';
    }

    protected function entityTable(): string
    {
        return 'collections';
    }

    protected function translationTable(): string
    {
        return 'collection_translations';
    }

    protected function foreignKey(): string
    {
        return 'collection_id';
    }

    protected function fields(): array
    {
        return ['title', 'description', 'seo_title', 'seo_description'];
    }

    protected function titleField(): string
    {
        return 'title';
    }

    protected function contentDescription(): string
    {
        return 'ecommerce merchandising collection and SEO metadata';
    }
}
