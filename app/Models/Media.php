<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Support\Str;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Concerns\StoreScoped;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

final class Media extends BaseMedia
{
    use HasPublicId, StoreScoped;

    protected static function booted(): void
    {
        self::creating(function (self $media): void {
            $media->store_id ??= app(StoreContext::class)->require()->getKey();
            $media->uuid ??= (string) Str::uuid();
        });
    }
}
