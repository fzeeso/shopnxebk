<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'taxonomy_id',
    'parent_id',
    'name',
    'slug',
    'code',
    'level',
    'path',
    'description',
    'is_active',
    'position',
])]
final class PlatformTaxonomyNode extends Model
{
    use HasPublicId;

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(PlatformTaxonomy::class, 'taxonomy_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(PlatformTaxonomyCustomField::class, 'taxonomy_node_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'platform_taxonomy_node_id');
    }

    public function productTypes(): HasMany
    {
        return $this->hasMany(ProductType::class, 'platform_taxonomy_node_id');
    }

    public function parentPublicId(): ?string
    {
        return $this->parent?->public_id;
    }

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
