<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Themes\Models\ThemeSubmission;

/** @extends JsonResource<ThemeSubmission> */
final class ThemeSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'submission_number' => $this->submission_number,
            'status' => $this->status,
            'submitted_by_user_id' => $this->whenLoaded('submitter', fn () => $this->submitter?->public_id),
            'assigned_reviewer_user_id' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->public_id),
            'automated_results' => $this->automated_results,
            'review_notes' => $this->review_notes,
            'rejection_codes' => $this->rejection_codes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'review_started_at' => $this->review_started_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
        ];
    }
}
