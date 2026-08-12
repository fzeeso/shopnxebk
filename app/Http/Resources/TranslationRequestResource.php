<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\TranslationRequestStatus;
use App\Models\TranslationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<TranslationRequest> */
final class TranslationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status_url' => route('api.v1.store.translation-requests.show', [
                'translationRequest' => $this->public_id,
            ], absolute: false),
            'content_type' => $this->content_type,
            'source_locale' => $this->source_locale,
            'target_locales' => $this->target_locales,
            'status' => $this->statusValue(),
            'attempts' => $this->attempts,
            'error' => $this->status === TranslationRequestStatus::Failed
                ? 'Automatic translation failed. The saved source content was not affected.'
                : null,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
