<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Product;
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

        $result = [
            'product' => $product === null ? null : new ProductResource($product),
            'revision' => $product?->updated_at?->toIso8601String(),
            'sections' => [
                'images' => ProductImageResource::collection($sections['images']),
                'media' => ProductMediaResource::collection($sections['media']),
                'custom_fields' => ProductCustomFieldValueResource::collection($sections['custom_fields']),
                'options' => ProductOptionResource::collection($sections['options']),
                'variants' => ProductVariantResource::collection($sections['variants']),
                'shared_options' => ProductSharedOptionAssignmentResource::collection($sections['shared_options']),
                'modifier_groups' => ProductModifierGroupResource::collection($sections['modifier_groups']),
                'modifiers' => ProductModifierAssignmentResource::collection($sections['modifiers']),
            ],
            'section_meta' => $this->resource['section_meta'],
            'capabilities' => [
                'writable_sections' => [
                    'product', 'images', 'media', 'custom_fields', 'options', 'variants',
                    'shared_options', 'modifier_groups', 'modifiers',
                ],
                'partial_section_saves' => true,
                'optimistic_concurrency' => true,
                'binary_uploads_are_separate' => true,
            ],
        ];

        if (array_key_exists('reference_data', $this->resource)) {
            /** @var array<string, mixed> $references */
            $references = $this->resource['reference_data'];
            $result['reference_data'] = [
                ...$references,
                'fulfillment_types' => FulfillmentTypeResource::collection($references['fulfillment_types']),
                'custom_fields' => CustomFieldDefinitionResource::collection($references['custom_fields']),
                'shared_options' => SharedProductOptionResource::collection($references['shared_options']),
                'modifiers' => ModifierDefinitionResource::collection($references['modifiers']),
                'currencies' => CurrencyResource::collection($references['currencies']),
            ];
        }
        if (array_key_exists('saved_sections', $this->resource)) {
            $result['saved_sections'] = $this->resource['saved_sections'];
            $result['references'] = $this->resource['references'] ?? [];
        }

        return $result;
    }
}
