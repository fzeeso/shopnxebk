<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaVariantName;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'media_id',
    'variant',
    'disk',
    'path',
    'mime_type',
    'size',
    'width',
    'height',
    'metadata',
])]
final class MediaVariant extends Model
{
    use HasPublicId;

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    protected function casts(): array
    {
        return [
            'variant' => MediaVariantName::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }
}
