<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'taxonomy_node_id',
    'custom_field_definition_id',
    'is_required',
    'is_filterable',
    'is_searchable',
    'is_variant',
    'position',
])]
final class PlatformTaxonomyCustomField extends Model
{
    public function taxonomyNode(): BelongsTo
    {
        return $this->belongsTo(PlatformTaxonomyNode::class, 'taxonomy_node_id');
    }

    public function customFieldDefinition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id');
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'is_searchable' => 'boolean',
            'is_variant' => 'boolean',
            'position' => 'integer',
        ];
    }
}
