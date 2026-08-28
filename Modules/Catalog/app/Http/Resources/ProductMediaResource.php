<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<Media> */
final class ProductMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new MediaResource($this->resource))->toArray($request),
            'attachment' => [
                'sort_order' => (int) $this->pivot->sort_order,
                'is_primary' => (bool) $this->pivot->is_primary,
            ],
        ];
    }
}
