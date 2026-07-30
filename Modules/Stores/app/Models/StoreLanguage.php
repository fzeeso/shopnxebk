<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Settings\Models\Language;

#[Fillable(['store_id', 'language_id', 'is_default', 'is_active'])]
final class StoreLanguage extends Model
{
    protected $table = 'store_languages';

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
