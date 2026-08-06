<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\StorePolicy;

/** @extends JsonResource<StorePolicy> */
final class StorePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'store_id' => $this->whenLoaded('store', fn () => $this->store?->public_id),
            'policy_type' => new PolicyTypeResource($this->whenLoaded('policyType')),
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->resource->statusValue(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->public_id),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater?->public_id),
            'translations' => StorePolicyTranslationResource::collection($this->whenLoaded('translations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
