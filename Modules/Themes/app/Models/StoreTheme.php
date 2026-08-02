<?php

declare(strict_types=1);

namespace Modules\Themes\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Themes\Enums\StoreThemeStatus;

#[Fillable(['store_id', 'theme_id', 'theme_version_id', 'theme_license_id', 'parent_store_theme_id', 'installed_by_user_id', 'name', 'status', 'installed_from', 'settings_data', 'template_data', 'custom_css', 'customization_object_key', 'customization_revision', 'installed_at', 'published_at'])]
final class StoreTheme extends Model
{
    use HasPublicId, SoftDeletes;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function themeVersion(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(ThemeLicense::class, 'theme_license_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_store_theme_id');
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by_user_id');
    }

    public function statusValue(): string
    {
        return $this->status instanceof StoreThemeStatus ? $this->status->value : (string) $this->status;
    }

    protected function casts(): array
    {
        return [
            'status' => StoreThemeStatus::class,
            'settings_data' => 'array',
            'template_data' => 'array',
            'customization_revision' => 'integer',
            'installed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
