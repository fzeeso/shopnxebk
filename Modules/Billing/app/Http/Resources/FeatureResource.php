<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Models\Feature;

/** @extends JsonResource<Feature> */
final class FeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'value_type' => $this->resource->valueTypeValue(),
            'unit' => $this->unit,
            'is_addon_eligible' => $this->is_addon_eligible,
            'is_active' => $this->is_active,
        ];
    }
}
