<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fulfillment_type_id', 'locale', 'name', 'description'])]
final class FulfillmentTypeTranslation extends Model
{
    public $timestamps = false;

    public function fulfillmentType(): BelongsTo
    {
        return $this->belongsTo(FulfillmentType::class);
    }
}
