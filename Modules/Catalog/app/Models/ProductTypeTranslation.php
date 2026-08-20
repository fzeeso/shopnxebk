<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_type_id', 'store_id', 'locale', 'name', 'slug', 'description', 'lock_it'])]
final class ProductTypeTranslation extends Model
{
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
