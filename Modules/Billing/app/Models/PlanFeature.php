<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Enums\BillingInterval;

#[Fillable([
    'plan_id',
    'feature_id',
    'value',
    'is_included',
    'is_addon',
    'addon_price_amount',
    'addon_currency_code',
    'addon_billing_interval',
    'sort_order',
])]
final class PlanFeature extends Model
{
    use HasPublicId;

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public function addonBillingIntervalValue(): ?string
    {
        if ($this->addon_billing_interval === null) {
            return null;
        }

        return $this->addon_billing_interval instanceof BillingInterval
            ? $this->addon_billing_interval->value
            : (string) $this->addon_billing_interval;
    }

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_included' => 'boolean',
            'is_addon' => 'boolean',
            'addon_price_amount' => 'integer',
            'addon_billing_interval' => BillingInterval::class,
            'sort_order' => 'integer',
        ];
    }
}
