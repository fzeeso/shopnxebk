<?php

declare(strict_types=1);

namespace Modules\Themes\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Authentication\Models\User;

#[Fillable(['owner_user_id', 'publisher_type', 'display_name', 'slug', 'status', 'support_email', 'support_url', 'website_url', 'payout_account_reference', 'default_commission_bps', 'verified_at', 'terms_accepted_at'])]
final class ThemePublisher extends Model
{
    use HasPublicId, SoftDeletes;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class, 'publisher_id');
    }

    protected function casts(): array
    {
        return [
            'default_commission_bps' => 'integer',
            'verified_at' => 'immutable_datetime',
            'terms_accepted_at' => 'immutable_datetime',
        ];
    }
}
