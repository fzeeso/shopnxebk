<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'is_system', 'sort_order'])]
final class PolicyType extends Model
{
    use HasPublicId;

    public function policies(): HasMany
    {
        return $this->hasMany(StorePolicy::class);
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
