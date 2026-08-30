<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Enums\CustomerGroupDiscountTarget;
use Modules\Customers\Models\CustomerGroupDiscount;

/** @extends JsonResource<CustomerGroupDiscount> */
final class CustomerGroupDiscountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $targetId = $this->target_type === CustomerGroupDiscountTarget::Category
            ? $this->category?->public_id
            : $this->product?->public_id;

        return [
            'id' => $this->public_id,
            'target_type' => $this->target_type->value,
            'target_id' => $targetId,
            'discount_percentage' => $this->discount_percentage,
            'applies_to' => $this->applies_to->value,
            'discount_method' => $this->discount_method,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
