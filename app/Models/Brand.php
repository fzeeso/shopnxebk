<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\CustomObjectReference;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['store_id', 'logo_url', 'website_url', 'origin', 'is_active', 'sort_order'])]
final class Brand extends Model implements HasMedia
{
    use HasPublicId, InteractsWithMedia, StoreScoped;

    public const MEDIA_IMAGE = 'image';

    public const MEDIA_BANNER = 'banner';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class)->orderBy('locale');
    }

    public function customObjectReferences(): HasMany
    {
        return $this->hasMany(CustomObjectReference::class, 'source_id')
            ->where('source_type', 'brand')
            ->orderBy('custom_field_definition_id')->orderBy('sort_order')->orderBy('id');
    }

    public function registerMediaCollections(): void
    {
        foreach ([self::MEDIA_IMAGE, self::MEDIA_BANNER] as $collection) {
            $this->addMediaCollection($collection)
                ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
                ->singleFile();
        }
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
