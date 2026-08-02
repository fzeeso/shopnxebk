<?php

declare(strict_types=1);

namespace Modules\Themes\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;

#[Fillable(['theme_id', 'store_id', 'license_type', 'status', 'billing_order_item_id', 'purchased_by_user_id', 'issued_at', 'trial_expires_at', 'transferred_from_license_id', 'revoked_at', 'refunded_at'])]
final class ThemeLicense extends Model
{
    use HasPublicId;

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by_user_id');
    }

    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_from_license_id');
    }

    public function installations(): HasMany
    {
        return $this->hasMany(StoreTheme::class);
    }

    protected function casts(): array
    {
        return [
            'billing_order_item_id' => 'integer',
            'issued_at' => 'immutable_datetime',
            'trial_expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
