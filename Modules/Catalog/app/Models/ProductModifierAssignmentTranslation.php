<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'product_modifier_assignment_id', 'locale', 'name_override', 'description_override', 'placeholder_override', 'help_text_override', 'lock_it'])]
final class ProductModifierAssignmentTranslation extends Model
{
    use StoreScoped;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProductModifierAssignment::class, 'product_modifier_assignment_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
