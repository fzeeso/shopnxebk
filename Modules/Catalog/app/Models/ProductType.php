<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable(['store_id', 'code', 'platform_taxonomy_node_id', 'is_active', 'sort_order'])]
final class ProductType extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function platformTaxonomyNode(): BelongsTo
    {
        return $this->belongsTo(PlatformTaxonomyNode::class, 'platform_taxonomy_node_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTypeTranslation::class)->orderBy('locale');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'platform_taxonomy_node_id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
