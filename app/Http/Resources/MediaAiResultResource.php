<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MediaAiResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<MediaAiResult> */
final class MediaAiResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'media_id' => $this->media?->public_id,
            'provider' => $this->provider,
            'model' => $this->model,
            'operation' => $this->operation,
            'status' => $this->status,
            'result' => $this->result,
            'confidence' => $this->confidence === null ? null : (float) $this->confidence,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
