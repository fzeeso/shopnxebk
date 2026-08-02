<?php

declare(strict_types=1);

namespace Modules\Themes\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Authentication\Models\User;
use Modules\Themes\Enums\ThemeVersionStatus;

#[Fillable(['theme_id', 'version', 'status', 'engine_version', 'minimum_platform_version', 'maximum_platform_version', 'source_archive_object_key', 'compiled_artifact_object_key', 'package_sha256', 'package_size_bytes', 'uncompressed_size_bytes', 'file_count', 'manifest', 'settings_schema', 'validation_report', 'release_notes', 'uploaded_by_user_id', 'approved_by_user_id', 'approved_at', 'published_at'])]
final class ThemeVersion extends Model
{
    use HasPublicId;

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ThemeSubmission::class)->orderByDesc('submission_number');
    }

    public function statusValue(): string
    {
        return $this->status instanceof ThemeVersionStatus ? $this->status->value : (string) $this->status;
    }

    protected function casts(): array
    {
        return [
            'status' => ThemeVersionStatus::class,
            'package_size_bytes' => 'integer',
            'uncompressed_size_bytes' => 'integer',
            'file_count' => 'integer',
            'manifest' => 'array',
            'settings_schema' => 'array',
            'validation_report' => 'array',
            'approved_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
