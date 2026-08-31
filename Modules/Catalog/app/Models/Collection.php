<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable([
    'store_id',
    'parent_id',
    'image_url',
    'is_active',
    'sort_order',
    'collection_type',
    'rules_match_type',
    'ai_prompt',
    'ai_model',
    'ai_status',
    'ai_last_run_at',
    'ai_error_message',
])]
final class Collection extends Model
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
        return $this->hasMany(CollectionTranslation::class)->orderBy('locale');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CollectionRule::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function aiJobs(): HasMany
    {
        return $this->hasMany(CollectionAiJob::class)->latest('created_at')->latest('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_collections')
            ->withPivot(['store_id', 'sort_order', 'added_by', 'is_pinned'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('products.id');
    }

    public function customObjectReferences(): HasMany
    {
        return $this->hasMany(CustomObjectReference::class, 'source_id')
            ->where('source_type', 'collection')
            ->orderBy('custom_field_definition_id')->orderBy('sort_order')->orderBy('id');
    }

    public function parentPublicId(): ?string
    {
        return $this->parent?->public_id;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'ai_last_run_at' => 'immutable_datetime',
        ];
    }
}
