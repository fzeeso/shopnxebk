<?php

declare(strict_types=1);

namespace Modules\Themes\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Themes\Enums\ThemeSourceType;
use Modules\Themes\Enums\ThemeStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['publisher_id', 'owner_store_id', 'created_by_user_id', 'name', 'slug', 'summary', 'description', 'source_type', 'visibility', 'commercial_type', 'status', 'price_amount_minor', 'price_currency', 'current_version_id', 'support_email', 'support_url', 'documentation_url', 'demo_url', 'listing_metadata', 'is_featured', 'published_at'])]
final class Theme extends Model implements HasMedia
{
    use HasPublicId, InteractsWithMedia, SoftDeletes;

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(ThemePublisher::class, 'publisher_id');
    }

    public function ownerStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'owner_store_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ThemeVersion::class)->orderByDesc('created_at');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ThemeCategory::class, 'theme_category_assignments', 'theme_id', 'category_id')
            ->withPivot(['is_primary', 'sort_order']);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(ThemeLicense::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(StoreTheme::class);
    }

    public function registerMediaCollections(): void
    {
        foreach (['icon', 'cover', 'desktop_screenshots', 'mobile_screenshots', 'preview_video', 'documentation_assets'] as $collection) {
            $this->addMediaCollection($collection);
        }
    }

    public function sourceTypeValue(): string
    {
        return $this->source_type instanceof ThemeSourceType ? $this->source_type->value : (string) $this->source_type;
    }

    public function statusValue(): string
    {
        return $this->status instanceof ThemeStatus ? $this->status->value : (string) $this->status;
    }

    protected function casts(): array
    {
        return [
            'source_type' => ThemeSourceType::class,
            'status' => ThemeStatus::class,
            'price_amount_minor' => 'integer',
            'listing_metadata' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }
}
