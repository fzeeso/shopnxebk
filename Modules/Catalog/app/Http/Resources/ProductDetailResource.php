<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\ProductDetailReferenceCache;
use Modules\Settings\Http\Resources\CurrencyResource;

/** @extends JsonResource<array<string, mixed>> */
final class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Product|null $product */
        $product = $this->resource['product'] ?? null;
        /** @var array<string, mixed> $sections */
        $sections = $this->resource['sections'];
        $collectionResources = [
            'images' => ProductImageResource::class,
            'media' => ProductMediaResource::class,
            'custom_fields' => ProductCustomFieldValueResource::class,
            'custom_objects' => CustomObjectReferenceResource::class,
            'options' => ProductOptionResource::class,
            'variants' => ProductVariantResource::class,
            'shared_options' => ProductSharedOptionAssignmentResource::class,
            'modifier_groups' => ProductModifierGroupResource::class,
            'modifiers' => ProductModifierAssignmentResource::class,
        ];
        $renderedSections = [];
        foreach ($sections as $key => $data) {
            $resource = $collectionResources[$key] ?? null;
            $renderedSections[$key] = $resource === null ? $data : $resource::collection($data);
        }

        $result = [
            'product' => $product === null ? null : new ProductResource($product),
            'revision' => $product?->updated_at?->toIso8601String(),
            'sections' => $renderedSections,
            'section_meta' => $this->resource['section_meta'],
            'capabilities' => [
                'writable_sections' => $this->resource['writable_sections']
                    ?? ['product', ...array_keys($sections)],
                'partial_section_saves' => true,
                'optimistic_concurrency' => true,
                'binary_uploads_are_separate' => true,
            ],
        ];

        if (array_key_exists('reference_data', $this->resource)) {
            /** @var array<string, mixed>|Closure(): array<string, mixed> $referenceSource */
            $referenceSource = $this->resource['reference_data'];
            $render = static function () use ($referenceSource, $request): array {
                $references = $referenceSource instanceof Closure ? $referenceSource() : $referenceSource;

                return [
                    ...$references,
                    'fulfillment_types' => FulfillmentTypeResource::collection($references['fulfillment_types'])->resolve($request),
                    'custom_fields' => CustomFieldDefinitionResource::collection($references['custom_fields'])->resolve($request),
                    'custom_object_types' => CustomObjectTypeResource::collection(
                        $references['custom_object_types'] ?? [],
                    )->resolve($request),
                    'shared_options' => SharedProductOptionResource::collection($references['shared_options'])->resolve($request),
                    'modifiers' => ModifierDefinitionResource::collection($references['modifiers'])->resolve($request),
                    'currencies' => CurrencyResource::collection($references['currencies'])->resolve($request),
                ];
            };
            /** @var array{store_id?: int, limit?: int} $cacheContext */
            $cacheContext = $this->resource['reference_cache'] ?? [];
            $storeId = (int) ($cacheContext['store_id'] ?? 0);
            $limit = (int) ($cacheContext['limit'] ?? 250);
            $result['reference_data'] = $storeId > 0
                ? app(ProductDetailReferenceCache::class)->remember($storeId, $limit, $render)
                : $render();
        }
        if (array_key_exists('saved_sections', $this->resource)) {
            $result['saved_sections'] = $this->resource['saved_sections'];
            $result['references'] = $this->resource['references'] ?? [];
        }

        return $result;
    }
}
