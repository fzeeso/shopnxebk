<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\FulfillmentType;
use Modules\Catalog\Models\FulfillmentTypeTranslation;

/** @extends JsonResource<FulfillmentType> */
final class FulfillmentTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'code' => $this->code,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'translations' => $this->translations->map(
                static fn (FulfillmentTypeTranslation $translation): array => [
                    'id' => $translation->getKey(),
                    'locale' => $translation->locale,
                    'name' => $translation->name,
                    'description' => $translation->description,
                ],
            )->values(),
        ];
    }
}
