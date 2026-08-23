<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable(['store_id', 'image_id', 'locale', 'alt_text', 'lock_it'])]
final class ProductImageTranslation extends Model
{
    use StoreScoped;

    public $incrementing = false;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
