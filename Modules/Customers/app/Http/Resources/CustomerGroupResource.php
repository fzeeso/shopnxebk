<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Resources;

use App\Http\Resources\TranslationRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Models\CustomerGroup;

/** @extends JsonResource<CustomerGroup> */
final class CustomerGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'default_discount_percentage' => $this->default_discount_percentage,
            'discount_method' => $this->discount_method,
            'is_default' => $this->is_default,
            'category_access_type' => $this->category_access_type->value,
            'customer_count' => $this->whenCounted('customers'),
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('public_id')->values()),
            'translations' => CustomerGroupTranslationResource::collection($this->whenLoaded('translations')),
            'discounts' => CustomerGroupDiscountResource::collection($this->whenLoaded('discounts')),
            'translation_request' => $this->when(
                $this->resource->relationLoaded('translationRequest'),
                fn () => $this->resource->getRelation('translationRequest') === null
                    ? null
                    : new TranslationRequestResource($this->resource->getRelation('translationRequest')),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
