<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

final class Media extends BaseMedia
{
    use HasPublicId;

    /** @var array<string, string> */
    protected $casts = [
        'manipulations' => 'array',
        'custom_properties' => 'array',
        'generated_conversions' => 'array',
        'responsive_images' => 'array',
        'metadata' => 'array',
        'status' => MediaStatus::class,
        'visibility' => MediaVisibility::class,
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'decimal:3',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class)->orderBy('width');
    }

    public function aiResults(): HasMany
    {
        return $this->hasMany(MediaAiResult::class)->latest('id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class)->latest('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_media')
            ->using(ProductMedia::class)
            ->withPivot(['id', 'store_id', 'sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function productVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_media')
            ->using(ProductVariantMedia::class)
            ->withPivot(['id', 'store_id', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function scopeForStore(Builder $query, Store|int $store): Builder
    {
        return $query->where($query->qualifyColumn('store_id'), $store instanceof Store ? $store->getKey() : $store);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), MediaStatus::Ready->value);
    }

    protected static function booted(): void
    {
        self::addGlobalScope('store', function (Builder $builder): void {
            $storeId = app(StoreContext::class)->id();
            if ($storeId !== null) {
                $builder->where($builder->qualifyColumn('store_id'), $storeId);
            }
        });

        self::saving(function (self $media): void {
            $storeId = app(StoreContext::class)->id();
            if (! $media->exists && $media->store_id === null && $storeId !== null) {
                $media->store_id = $storeId;
            }
            if ($storeId !== null && (int) $media->store_id !== $storeId) {
                throw (new ModelNotFoundException)->setModel(self::class, [$media->getKey()]);
            }
        });

        self::deleting(function (self $media): void {
            $storeId = app(StoreContext::class)->id();
            if ($storeId !== null && (int) $media->store_id !== $storeId) {
                throw (new ModelNotFoundException)->setModel(self::class, [$media->getKey()]);
            }
        });

        self::creating(function (self $media): void {
            $media->uuid ??= (string) Str::uuid();
            $media->status ??= MediaStatus::Ready;
            $media->visibility ??= MediaVisibility::Private;
            $media->filename ??= $media->file_name;
            $media->original_filename ??= $media->file_name;
            $media->extension ??= pathinfo((string) $media->file_name, PATHINFO_EXTENSION) ?: null;

            if ($media->directory === null && $media->store_id !== null && $media->public_id !== null) {
                $storePublicId = Store::query()->whereKey($media->store_id)->value('public_id');
                if ($storePublicId !== null) {
                    $media->directory = sprintf(
                        'stores/%s/media/%s/%s/%s',
                        $storePublicId,
                        now()->format('Y'),
                        now()->format('m'),
                        $media->public_id,
                    );
                }
            }

            if ($media->path === null && $media->directory !== null && $media->filename !== null) {
                $media->path = rtrim((string) $media->directory, '/').'/'.$media->filename;
            }

            if ($media->directory !== null && $media->path !== null) {
                $customProperties = is_array($media->custom_properties) ? $media->custom_properties : [];
                $customProperties['shopnxe_storage'] = [
                    'version' => 1,
                    'directory' => $media->directory,
                    'path' => $media->path,
                ];
                $media->custom_properties = $customProperties;
            }
        });
    }
}
