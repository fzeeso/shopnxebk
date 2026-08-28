<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Brand;
use App\Support\Translations\StoreTranslationLanguages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\CustomFieldDefinition;
use Modules\Catalog\Models\ModifierDefinition;
use Modules\Catalog\Models\PlatformTaxonomyNode;
use Modules\Catalog\Models\ProductCustomFieldValue;
use Modules\Catalog\Models\ProductImage;
use Modules\Catalog\Models\ProductModifierAssignment;
use Modules\Catalog\Models\ProductModifierGroup;
use Modules\Catalog\Models\ProductOption;
use Modules\Catalog\Models\ProductSharedOptionAssignment;
use Modules\Catalog\Models\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\SharedProductOption;
use Modules\Settings\Models\Currency;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ProductDetailReadService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private ProductManagementService $products,
        private FulfillmentTypeManagementService $fulfillmentTypes,
        private StoreTranslationLanguages $languages,
        private ProductDetailSectionRegistry $sectionRegistry,
    ) {}

    /** @return array<string, mixed> */
    public function bootstrap(User $user, int $referenceLimit = 250): array
    {
        $store = $this->store($user);

        $sections = $this->emptySections();
        $sectionMeta = [];
        foreach ($this->sectionRegistry->all() as $provider) {
            $payload = $provider->bootstrap($user, $store, $referenceLimit);
            $sections[$provider->key()] = $payload->data;
            $sectionMeta[$provider->key()] = $payload->meta($referenceLimit);
        }

        return [
            'product' => null,
            'sections' => $sections,
            'section_meta' => $sectionMeta,
            'reference_data' => $this->referenceData($user, $store, $referenceLimit),
        ];
    }

    /** @return array<string, mixed> */
    public function show(
        User $user,
        string $productPublicId,
        int $sectionLimit = 100,
        bool $withReferenceData = true,
        int $referenceLimit = 250,
    ): array {
        $store = $this->store($user);
        $product = $this->products->show($user, $productPublicId);
        $productId = (int) $product->getKey();
        $storeId = (int) $store->getKey();

        [$images, $imageMeta] = $this->limited(
            ProductImage::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->with(['product', 'variant', 'translations'])
                ->orderBy('position')->orderBy('id'),
            $sectionLimit,
        );
        [$options, $optionMeta] = $this->limited(
            ProductOption::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->with(['product', 'translations', 'values.translations'])
                ->orderBy('position')->orderBy('id'),
            $sectionLimit,
        );
        [$variants, $variantMeta] = $this->limited(
            ProductVariant::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->with([
                    'product', 'preferredImage', 'translations', 'optionValues.translations',
                    'optionValues.option.translations',
                ])
                ->orderBy('position')->orderBy('id'),
            $sectionLimit,
        );
        [$customFields, $customFieldMeta] = $this->limited(
            ProductCustomFieldValue::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->with([
                    'product', 'variant', 'definition.translations', 'definition.options.translations',
                    'selectedOption.translations', 'selectedOptions.translations', 'translations',
                ])
                ->orderBy('variant_id')->orderBy('definition_id')->orderBy('id'),
            $sectionLimit,
        );
        [$sharedOptions, $sharedOptionMeta] = $this->limited(
            ProductSharedOptionAssignment::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->with(['product', 'option.translations', 'option.values.translations'])
                ->orderBy('position')->orderBy('id'),
            $sectionLimit,
        );
        [$modifierGroups, $modifierGroupMeta] = $this->limited(
            ProductModifierGroup::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->with('translations')
                ->orderBy('sort_order')->orderBy('id'),
            $sectionLimit,
        );
        [$modifiers, $modifierMeta] = $this->limited(
            ProductModifierAssignment::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->with([
                    'modifier.translations', 'modifier.values.translations', 'modifier.values.priceAdjustments',
                    'modifier.validationRules.translations', 'modifier.priceAdjustments', 'group.translations',
                    'translations', 'valueAssignments.value.translations', 'priceOverrides',
                    'valuePriceOverrides.value',
                ])
                ->orderBy('sort_order')->orderBy('id'),
            $sectionLimit,
        );

        $mediaQuery = $product->media()->with('variants.media');
        $mediaTotal = $mediaQuery->count();
        $media = $mediaQuery->limit($sectionLimit)->get();

        $extensionSections = [];
        $extensionMeta = [];
        foreach ($this->sectionRegistry->all() as $provider) {
            $payload = $provider->read($user, $store, $product, $sectionLimit);
            $extensionSections[$provider->key()] = $payload->data;
            $extensionMeta[$provider->key()] = $payload->meta($sectionLimit);
        }

        $result = [
            'product' => $product,
            'sections' => [
                'images' => $images,
                'media' => $media,
                'custom_fields' => $customFields,
                'options' => $options,
                'variants' => $variants,
                'shared_options' => $sharedOptions,
                'modifier_groups' => $modifierGroups,
                'modifiers' => $modifiers,
                ...$extensionSections,
            ],
            'section_meta' => [
                'images' => $imageMeta,
                'media' => $this->meta($mediaTotal, $media->count(), $sectionLimit),
                'custom_fields' => $customFieldMeta,
                'options' => $optionMeta,
                'variants' => $variantMeta,
                'shared_options' => $sharedOptionMeta,
                'modifier_groups' => $modifierGroupMeta,
                'modifiers' => $modifierMeta,
                ...$extensionMeta,
            ],
        ];
        if ($withReferenceData) {
            $result['reference_data'] = $this->referenceData($user, $store, $referenceLimit);
        }

        return $result;
    }

    private function store(User $user): Store
    {
        $store = $this->context->require();
        $this->access->ensureCanView($user, $store);

        return $store;
    }

    /** @return array<string, mixed> */
    private function referenceData(User $user, Store $store, int $limit): array
    {
        $storeId = (int) $store->getKey();

        [$brands, $brandMeta] = $this->limited(
            Brand::query()->where('store_id', $storeId)->where('is_active', true)
                ->with('translations')->orderBy('sort_order')->orderBy('id'),
            $limit,
        );
        [$categories, $categoryMeta] = $this->limited(
            Category::query()->where('store_id', $storeId)->where('is_active', true)
                ->with(['parent', 'translations'])->orderBy('sort_order')->orderBy('id'),
            $limit,
        );
        [$productTypes, $productTypeMeta] = $this->limited(
            ProductType::query()->where('store_id', $storeId)->where('is_active', true)
                ->with(['platformTaxonomyNode', 'translations'])->orderBy('sort_order')->orderBy('id'),
            $limit,
        );
        [$taxonomyNodes, $taxonomyMeta] = $this->limited(
            PlatformTaxonomyNode::query()->where('is_active', true)
                ->with('parent')->orderBy('path')->orderBy('id'),
            $limit,
        );
        [$customFields, $customFieldMeta] = $this->limited(CustomFieldDefinition::query()->where('store_id', $storeId)
            ->with(['translations', 'options.translations'])->withCount('values')
            ->orderBy('position')->orderBy('id'), $limit);
        [$sharedOptions, $sharedOptionMeta] = $this->limited(SharedProductOption::query()->where('store_id', $storeId)
            ->with(['translations', 'values.translations'])->withCount('assignments')
            ->orderBy('position')->orderBy('id'), $limit);
        [$modifiers, $modifierMeta] = $this->limited(ModifierDefinition::query()->where('store_id', $storeId)->where('is_active', true)
            ->with([
                'category.translations', 'translations', 'values.translations', 'values.image',
                'values.priceAdjustments', 'validationRules.translations', 'priceAdjustments',
            ])->orderBy('sort_order')->orderBy('id'), $limit);
        $currencies = Currency::query()->where('is_active', true)->orderByDesc('is_base')->orderBy('code')->get();

        return [
            'limit' => $limit,
            'meta' => [
                'brands' => $brandMeta,
                'categories' => $categoryMeta,
                'product_types' => $productTypeMeta,
                'platform_taxonomy_nodes' => $taxonomyMeta,
                'custom_fields' => $customFieldMeta,
                'shared_options' => $sharedOptionMeta,
                'modifiers' => $modifierMeta,
            ],
            'brands' => $brands->map(static fn (Brand $brand): array => [
                'id' => $brand->public_id,
                'logo_url' => $brand->logo_url,
                'translations' => $brand->translations->map(static fn ($translation): array => [
                    'locale' => $translation->locale,
                    'name' => $translation->name,
                ])->values(),
            ])->values(),
            'categories' => $categories->map(static fn (Category $category): array => [
                'id' => $category->public_id,
                'parent_id' => $category->parentPublicId(),
                'translations' => $category->translations->map(static fn ($translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                ])->values(),
            ])->values(),
            'product_types' => $productTypes->map(static fn (ProductType $type): array => [
                'id' => $type->public_id,
                'code' => $type->code,
                'platform_taxonomy_node_id' => $type->platformTaxonomyNodePublicId(),
                'translations' => $type->translations->map(static fn ($translation): array => [
                    'locale' => $translation->locale,
                    'name' => $translation->name,
                ])->values(),
            ])->values(),
            'platform_taxonomy_nodes' => $taxonomyNodes->map(static fn (PlatformTaxonomyNode $node): array => [
                'id' => $node->public_id,
                'parent_id' => $node->parentPublicId(),
                'name' => $node->name,
                'code' => $node->code,
                'path' => $node->path,
                'level' => $node->level,
            ])->values(),
            'fulfillment_types' => $this->fulfillmentTypes->listStore($user),
            'custom_fields' => $customFields,
            'shared_options' => $sharedOptions,
            'modifiers' => $modifiers,
            'languages' => $this->languages->presentationFor($store),
            'currencies' => $currencies,
            'store_defaults' => [
                'currency_code' => $store->currency_code,
                'language_code' => $store->language_code,
                'timezone' => $store->timezone,
            ],
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return array{0: Collection<int, TModel>, 1: array{total: int, returned: int, limit: int, truncated: bool}}
     */
    private function limited(Builder $query, int $limit): array
    {
        $total = (clone $query)->reorder()->count();
        $items = $query->limit($limit)->get();

        return [$items, $this->meta($total, $items->count(), $limit)];
    }

    /** @return array{total: int, returned: int, limit: int, truncated: bool} */
    private function meta(int $total, int $returned, int $limit): array
    {
        return [
            'total' => $total,
            'returned' => $returned,
            'limit' => $limit,
            'truncated' => $total > $returned,
        ];
    }

    /** @return array<string, Collection<int, mixed>> */
    private function emptySections(): array
    {
        return [
            'images' => collect(),
            'media' => collect(),
            'custom_fields' => collect(),
            'options' => collect(),
            'variants' => collect(),
            'shared_options' => collect(),
            'modifier_groups' => collect(),
            'modifiers' => collect(),
        ];
    }
}
