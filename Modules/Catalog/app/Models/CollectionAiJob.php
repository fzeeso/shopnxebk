<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable([
    'store_id',
    'collection_id',
    'prompt',
    'model',
    'status',
    'result_rules',
    'matched_count',
    'error_message',
    'tokens_used',
    'completed_at',
])]
final class CollectionAiJob extends Model
{
    use HasPublicId, StoreScoped;

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    protected function casts(): array
    {
        return [
            'result_rules' => 'array',
            'matched_count' => 'integer',
            'tokens_used' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
