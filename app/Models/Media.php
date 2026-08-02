<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Support\Str;
use Modules\Stores\Contracts\StoreContext;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

final class Media extends BaseMedia
{
    use HasPublicId;

    protected static function booted(): void
    {
        self::addGlobalScope('store', function (Builder $builder): void {
            $storeId = app(StoreContext::class)->id();
            if ($storeId !== null) {
                $builder->where($builder->qualifyColumn('store_id'), $storeId);
            }
        });

        self::creating(function (self $media): void {
            $media->store_id ??= app(StoreContext::class)->id();
            $media->uuid ??= (string) Str::uuid();
        });
    }
}
