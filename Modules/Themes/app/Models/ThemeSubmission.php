<?php

declare(strict_types=1);

namespace Modules\Themes\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Authentication\Models\User;

#[Fillable(['theme_version_id', 'submission_number', 'status', 'submitted_by_user_id', 'assigned_reviewer_user_id', 'automated_results', 'review_notes', 'rejection_codes', 'submitted_at', 'review_started_at', 'decided_at'])]
final class ThemeSubmission extends Model
{
    use HasPublicId;

    public function themeVersion(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_user_id');
    }

    protected function casts(): array
    {
        return [
            'submission_number' => 'integer',
            'automated_results' => 'array',
            'rejection_codes' => 'array',
            'submitted_at' => 'immutable_datetime',
            'review_started_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }
}
