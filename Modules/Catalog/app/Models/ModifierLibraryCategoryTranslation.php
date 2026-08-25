<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'category_id', 'locale', 'name', 'description', 'lock_it'])]
final class ModifierLibraryCategoryTranslation extends Model
{
    use StoreScoped;

    public function category(): BelongsTo
    {
        return $this->belongsTo(ModifierLibraryCategory::class, 'category_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
