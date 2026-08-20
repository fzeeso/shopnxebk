<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'version', 'status', 'is_default'])]
final class PlatformTaxonomy extends Model
{
    use HasPublicId;

    public function nodes(): HasMany
    {
        return $this->hasMany(PlatformTaxonomyNode::class, 'taxonomy_id')
            ->orderBy('level')
            ->orderBy('position')
            ->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
