<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Models\Concerns\TenantScoped;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

final class Media extends BaseMedia
{
    use HasUuids, TenantScoped;

    protected static function booted(): void
    {
        self::creating(function (self $media): void {
            $media->tenant_id ??= app(TenantContext::class)->require()->getKey();
            $media->uuid ??= $media->newUniqueId();
        });
    }
}
