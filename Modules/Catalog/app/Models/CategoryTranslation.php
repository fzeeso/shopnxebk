<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id',
    'category_id',
    'locale',
    'title',
    'slug',
    'description',
    'image_url',
    'banner_url',
    'seo_title',
    'seo_description',
    'page_title',
    'search_keywords',
    'category_template',
    'lock_it',
])]
final class CategoryTranslation extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'category_id';

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
