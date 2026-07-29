<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Enums\LanguageDirection;

#[Fillable(['name', 'native_name', 'locale', 'direction', 'is_active'])]
final class Language extends Model
{
    use HasPublicId;

    public function storeLanguages(): HasMany
    {
        return $this->hasMany(StoreLanguage::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_languages')
            ->withPivot(['is_default', 'is_active'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'direction' => LanguageDirection::class,
            'is_active' => 'boolean',
        ];
    }
}
