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

#[Fillable(['store_id', 'name', 'type', 'position'])]
final class SharedProductOption extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(SharedProductOptionTranslation::class, 'option_id')->orderBy('locale');
    }

    public function values(): HasMany
    {
        return $this->hasMany(SharedProductOptionValue::class, 'option_id')->orderBy('position')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductSharedOptionAssignment::class, 'option_id')->orderBy('position')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
