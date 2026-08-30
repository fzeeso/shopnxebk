<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Models\Concerns\StoreScoped;

#[Fillable(['store_id', 'collection_id', 'field', 'operator', 'value', 'position'])]
final class CollectionRule extends Model
{
    use HasPublicId, StoreScoped;

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
