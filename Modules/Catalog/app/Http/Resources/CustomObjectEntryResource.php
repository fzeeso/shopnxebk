<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Modules\Catalog\Models\CustomObjectEntryTranslation;

final class CustomObjectEntryResource extends CustomObjectEntryOptionResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        return [
            ...$data,
            'type_id' => $this->whenLoaded('type', fn () => $this->type->public_id),
            'sort_order' => $this->sort_order,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(
                static fn (CustomObjectEntryTranslation $item): array => [
                    'locale' => $item->locale,
                    'name' => $item->name,
                    'description' => $item->description,
                    'lock_it' => $item->lock_it,
                ],
            )->values()),
            'values' => $this->whenLoaded('values', fn () => CustomObjectValueResource::collection($this->values)),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->public_id),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater?->public_id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
