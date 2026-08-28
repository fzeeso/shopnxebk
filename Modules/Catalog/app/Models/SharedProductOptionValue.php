<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable(['store_id', 'option_id', 'position', 'is_default'])]
final class SharedProductOptionValue extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(SharedProductOption::class, 'option_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(SharedProductOptionValueTranslation::class, 'option_value_id')->orderBy('locale');
    }

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'position' => 'integer'];
    }
}
