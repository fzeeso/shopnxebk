<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'media_id',
    'provider',
    'model',
    'operation',
    'status',
    'result',
    'confidence',
])]
final class MediaAiResult extends Model
{
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'confidence' => 'decimal:6',
        ];
    }
}
