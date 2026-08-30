<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Authentication\Models\User;
use Modules\Stores\Enums\PageStatus;
use Modules\Stores\Enums\PageType;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable([
    'store_id',
    'parent_id',
    'page_type',
    'status',
    'sort_order',
    'layout_key',
    'is_homepage',
    'customers_only',
    'seo_enabled',
    'external_url',
    'feed_url',
    'contact_email',
    'contact_fields',
    'published_at',
    'created_by',
    'updated_by',
])]
final class Page extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class)
            ->orderBy('language_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusValue(): string
    {
        return $this->status instanceof PageStatus ? $this->status->value : (string) $this->status;
    }

    public function typeValue(): string
    {
        return $this->page_type instanceof PageType ? $this->page_type->value : (string) $this->page_type;
    }

    protected function casts(): array
    {
        return [
            'page_type' => PageType::class,
            'status' => PageStatus::class,
            'sort_order' => 'integer',
            'is_homepage' => 'boolean',
            'customers_only' => 'boolean',
            'seo_enabled' => 'boolean',
            'contact_fields' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }
}
