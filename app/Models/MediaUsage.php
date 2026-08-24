<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'media_id',
    'store_id',
    'resource_type',
    'resource_id',
])]
final class MediaUsage extends Model
{
    use StoreScoped;

    public const UPDATED_AT = null;

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function casts(): array
    {
        return [
            'resource_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
