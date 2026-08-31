<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CustomObjectValue;
use Modules\Catalog\Models\CustomObjectValueTranslation;

/** @extends JsonResource<CustomObjectValue> */
final class CustomObjectValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CustomObjectValueTranslation|null $translation */
        $translation = $this->relationLoaded('translations')
            ? CustomObjectResourceSupport::resolved($this->translations, $request)
            : null;

        return [
            'id' => $this->public_id,
            'field_id' => $this->whenLoaded('field', fn () => $this->field->public_id),
            'field' => $this->whenLoaded('field', fn () => new CustomObjectFieldResource($this->field)),
            'value_text' => $translation?->value_text ?? $this->value_text,
            'value_number' => $this->value_number,
            'value_boolean' => $this->value_boolean,
            'value_date' => $this->value_date?->format('Y-m-d'),
            'value_datetime' => $this->value_datetime?->toIso8601String(),
            'value_json' => $translation?->value_json ?? $this->value_json,
            'media_id' => $this->whenLoaded('media', fn () => $this->media?->public_id),
            'entry_ids' => $this->whenLoaded('referencedEntries', fn () => $this->referencedEntries->pluck('public_id')->values()),
            'entries' => $this->whenLoaded('referencedEntries', fn () => CustomObjectEntryOptionResource::collection($this->referencedEntries)),
            'resolved_locale' => $translation?->locale,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(
                static fn (CustomObjectValueTranslation $item): array => [
                    'locale' => $item->locale,
                    'value_text' => $item->value_text,
                    'value_json' => $item->value_json,
                    'lock_it' => $item->lock_it,
                ],
            )->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
