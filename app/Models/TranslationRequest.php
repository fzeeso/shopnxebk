<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TranslationRequestStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'content_type',
    'content_id',
    'source_locale',
    'source_hash',
    'request_hash',
    'target_locales',
    'status',
    'attempts',
    'last_error',
    'requested_by',
    'queued_at',
    'started_at',
    'completed_at',
])]
final class TranslationRequest extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function statusValue(): string
    {
        return $this->status instanceof TranslationRequestStatus
            ? $this->status->value
            : (string) $this->status;
    }

    protected function casts(): array
    {
        return [
            'target_locales' => 'array',
            'status' => TranslationRequestStatus::class,
            'attempts' => 'integer',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
