<?php

declare(strict_types=1);

namespace Modules\Authentication\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Modules\Stores\Models\Store;

#[Fillable(['name', 'token', 'abilities', 'store_id', 'expires_at', 'metadata'])]
/** @property string|null $legacy_id @property int|null $store_id @property \Carbon\CarbonImmutable|null $expires_at */
final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasPublicId;

    public static function findToken($token)
    {
        if (! str_contains((string) $token, '|')) {
            return parent::findToken($token);
        }

        [$id, $plainTextToken] = explode('|', (string) $token, 2);
        $instance = ctype_digit($id)
            ? self::query()->find($id)
            : self::query()->where('legacy_id', $id)->first();

        if ($instance === null) {
            return null;
        }

        return hash_equals($instance->token, hash('sha256', $plainTextToken))
            ? $instance
            : null;
    }

    /** @return BelongsTo<Store, self> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'metadata' => 'array',
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
