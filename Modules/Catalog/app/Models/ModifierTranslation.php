<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'modifier_id', 'locale', 'name', 'description', 'placeholder', 'help_text', 'required_message', 'validation_message', 'lock_it'])]
final class ModifierTranslation extends Model
{
    use StoreScoped;

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(ModifierDefinition::class, 'modifier_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
