<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Billing\Enums\FeatureValueType;
use Modules\Billing\Models\Feature;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanFeature;

final readonly class PlanFeatureAdminService
{
    public function __construct(private PlatformPlanAccessService $access) {}

    /** @param array<string, mixed> $data */
    public function upsert(User $user, Plan $plan, Feature $feature, array $data): PlanFeature
    {
        $this->access->ensureCanManage($user);
        $this->validateValue($feature, $data['value'] ?? null);

        $isAddon = (bool) ($data['is_addon'] ?? false);
        if ($isAddon && ! $feature->is_addon_eligible) {
            throw ValidationException::withMessages([
                'is_addon' => ['This feature is not eligible to be sold as an add-on.'],
            ]);
        }

        if ($isAddon) {
            $data['is_included'] = false;
            if (isset($data['addon_price_amount'])) {
                $data['addon_currency_code'] ??= $plan->currency_code;
                $data['addon_billing_interval'] ??= $plan->billingIntervalValue();
            }
        } else {
            $data['addon_price_amount'] = null;
            $data['addon_currency_code'] = null;
            $data['addon_billing_interval'] = null;
        }

        return DB::transaction(function () use ($plan, $feature, $data): PlanFeature {
            $assignment = PlanFeature::query()->updateOrCreate(
                ['plan_id' => $plan->getKey(), 'feature_id' => $feature->getKey()],
                $data,
            );

            return $assignment->refresh()->load('feature');
        });
    }

    public function remove(User $user, Plan $plan, Feature $feature): void
    {
        $this->access->ensureCanManage($user);

        PlanFeature::query()
            ->where('plan_id', $plan->getKey())
            ->where('feature_id', $feature->getKey())
            ->delete();
    }

    private function validateValue(Feature $feature, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $valid = match ($feature->value_type) {
            FeatureValueType::Boolean => is_bool($value),
            FeatureValueType::Integer => is_int($value),
            FeatureValueType::Decimal => is_int($value) || is_float($value),
            FeatureValueType::Text => is_string($value),
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                'value' => ["The value must match the feature type [{$feature->valueTypeValue()}]."],
            ]);
        }
    }
}
