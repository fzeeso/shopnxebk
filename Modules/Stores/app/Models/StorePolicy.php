<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Authentication\Models\User;
use Modules\Stores\Enums\StorePolicyStatus;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'policy_type_id', 'title', 'slug', 'status', 'published_at', 'created_by', 'updated_by'])]
final class StorePolicy extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function policyType(): BelongsTo
    {
        return $this->belongsTo(PolicyType::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(StorePolicyTranslation::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusValue(): string
    {
        return $this->status instanceof StorePolicyStatus ? $this->status->value : (string) $this->status;
    }

    protected function casts(): array
    {
        return [
            'status' => StorePolicyStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }
}
