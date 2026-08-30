<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Resources;

use App\Http\Resources\TranslationRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Models\CustomerGroupTranslation;

/** @extends JsonResource<CustomerGroupTranslation> */
final class CustomerGroupTranslationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'language' => $this->whenLoaded('language', fn (): array => [
                'id' => $this->language->public_id,
                'name' => $this->language->name,
                'native_name' => $this->language->native_name,
                'locale' => $this->language->locale,
                'direction' => $this->language->direction->value,
            ]),
            'name' => $this->name,
            'lock_it' => $this->lock_it,
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
