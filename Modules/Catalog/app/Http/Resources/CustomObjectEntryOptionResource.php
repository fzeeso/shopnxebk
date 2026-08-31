<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CustomObjectEntry;
use Modules\Catalog\Models\CustomObjectEntryTranslation;

/** @extends JsonResource<CustomObjectEntry> */
class CustomObjectEntryOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CustomObjectEntryTranslation|null $translation */
        $translation = $this->relationLoaded('translations')
            ? CustomObjectResourceSupport::resolved($this->translations, $request)
            : null;

        return [
            'id' => $this->public_id,
            'handle' => $this->handle,
            'name' => $translation?->name,
            'description' => $translation?->description,
            'resolved_locale' => $translation?->locale,
            'status' => $this->status,
        ];
    }
}
