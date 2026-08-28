<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductSharedOptionAssignment;

/** @extends JsonResource<ProductSharedOptionAssignment> */
final class ProductSharedOptionAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'position' => $this->position,
            'product_id' => $this->whenLoaded('product', fn () => $this->product->public_id),
            'option' => $this->whenLoaded(
                'option',
                fn () => new SharedProductOptionResource($this->option),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
