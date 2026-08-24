<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MediaVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<MediaVariant> */
final class MediaVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'variant' => $this->variant->value,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'metadata' => $this->metadata,
            'content_url' => route('api.v1.store.media.content', [
                'media' => $this->media->public_id,
                'variant' => $this->variant->value,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
