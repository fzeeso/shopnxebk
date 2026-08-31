<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CustomObjectType;
use Modules\Catalog\Models\CustomObjectTypeTranslation;

/** @extends JsonResource<CustomObjectType> */
final class CustomObjectTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CustomObjectTypeTranslation|null $translation */
        $translation = $this->relationLoaded('translations')
            ? CustomObjectResourceSupport::resolved($this->translations, $request)
            : null;

        return [
            'id' => $this->public_id,
            'handle' => $this->handle,
            'status' => $this->status,
            'is_system' => $this->is_system,
            'name' => $translation?->name,
            'description' => $translation?->description,
            'resolved_locale' => $translation?->locale,
            'entries_count' => $this->whenCounted('entries'),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(
                static fn (CustomObjectTypeTranslation $item): array => [
                    'locale' => $item->locale,
                    'name' => $item->name,
                    'description' => $item->description,
                    'lock_it' => $item->lock_it,
                ],
            )->values()),
            'fields' => $this->whenLoaded('fields', fn () => CustomObjectFieldResource::collection($this->fields)),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->public_id),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater?->public_id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
