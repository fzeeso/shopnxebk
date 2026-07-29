<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;

#[Fillable([
    'name',
    'slug',
    'description',
    'best_for',
    'price_amount',
    'currency_code',
    'billing_interval',
    'is_custom_pricing',
    'status',
    'is_featured',
    'sort_order',
])]
final class Plan extends Model
{
    use HasPublicId;

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class)->orderBy('sort_order');
    }

    public function statusValue(): string
    {
        return $this->status instanceof PlanStatus ? $this->status->value : (string) $this->status;
    }

    public function billingIntervalValue(): ?string
    {
        if ($this->billing_interval === null) {
            return null;
        }

        return $this->billing_interval instanceof BillingInterval
            ? $this->billing_interval->value
            : (string) $this->billing_interval;
    }

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'billing_interval' => BillingInterval::class,
            'is_custom_pricing' => 'boolean',
            'status' => PlanStatus::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
