<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_id', 'brand_id', 'locale', 'name', 'slug', 'description', 'seo_title', 'seo_description', 'lock_it'])]
final class BrandTranslation extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'brand_id';

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
