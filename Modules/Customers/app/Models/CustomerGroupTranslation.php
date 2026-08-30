<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Settings\Models\Language;
use Modules\Stores\Models\Concerns\StoreScoped;
use Modules\Stores\Models\Store;

#[Fillable(['store_id', 'customer_group_id', 'language_id', 'name', 'lock_it'])]
final class CustomerGroupTranslation extends Model
{
    use HasPublicId, StoreScoped;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
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
