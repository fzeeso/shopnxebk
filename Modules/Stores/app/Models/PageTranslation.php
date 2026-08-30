<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Settings\Models\Language;

#[Fillable([
    'store_id',
    'page_id',
    'language_id',
    'title',
    'slug',
    'content',
    'summary',
    'seo_title',
    'seo_description',
    'seo_keywords',
    'search_keywords',
    'lock_it',
])]
final class PageTranslation extends Model
{
    use HasPublicId;

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }
}
