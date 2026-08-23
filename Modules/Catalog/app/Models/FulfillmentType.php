<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'is_active', 'sort_order'])]
final class FulfillmentType extends Model
{
    public function translations(): HasMany
    {
        return $this->hasMany(FulfillmentTypeTranslation::class)->orderBy('locale');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
