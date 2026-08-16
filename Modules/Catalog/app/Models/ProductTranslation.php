<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id',
    'product_id',
    'locale',
    'title',
    'slug',
    'description',
    'seo_title',
    'seo_description',
    'lock_it',
])]
final class ProductTranslation extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'product_id';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
