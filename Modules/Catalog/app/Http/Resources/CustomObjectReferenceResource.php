<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CustomObjectReference;

/** @extends JsonResource<CustomObjectReference> */
final class CustomObjectReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'source_type' => $this->source_type,
            'definition_id' => $this->whenLoaded('definition', fn () => $this->definition->public_id),
            'definition' => $this->whenLoaded('definition', fn () => new CustomFieldDefinitionResource($this->definition)),
            'type' => $this->whenLoaded('type', fn () => new CustomObjectTypeResource($this->type)),
            'entry' => $this->whenLoaded('entry', fn () => new CustomObjectEntryOptionResource($this->entry)),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
