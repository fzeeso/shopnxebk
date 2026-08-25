<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'code', 'sort_order', 'is_active'])]
final class ModifierLibraryCategory extends Model
{
    use HasPublicId, SoftDeletes, StoreScoped;

    public function translations(): HasMany
    {
        return $this->hasMany(ModifierLibraryCategoryTranslation::class, 'category_id')->orderBy('locale');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(ModifierDefinition::class, 'library_category_id')->orderBy('sort_order')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
