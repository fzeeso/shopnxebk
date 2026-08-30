<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CollectionAiJob;

/** @extends JsonResource<CollectionAiJob> */
final class CollectionAiJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'prompt' => $this->prompt,
            'model' => $this->model,
            'status' => $this->status,
            'result_rules' => $this->result_rules,
            'matched_count' => $this->matched_count,
            'error_message' => $this->error_message,
            'tokens_used' => $this->tokens_used,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
