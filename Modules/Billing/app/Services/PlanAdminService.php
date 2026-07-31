<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Billing\Models\Plan;

final readonly class PlanAdminService
{
    public function __construct(private PlatformPlanAccessService $access) {}

    /** @return LengthAwarePaginator<int, Plan> */
    public function list(User $user, int $perPage = 25): LengthAwarePaginator
    {
        $this->access->ensureCanManage($user);

        return Plan::query()
            ->with('planFeatures.feature')
            ->orderBy('sort_order')
            ->orderBy('price_amount')
            ->paginate($perPage);
    }

    public function view(User $user, Plan $plan): Plan
    {
        $this->access->ensureCanManage($user);

        return $plan->load('planFeatures.feature');
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Plan
    {
        $this->access->ensureCanManage($user);
        $data = $this->normalizePricing($data);

        return DB::transaction(fn (): Plan => Plan::query()
            ->create($data)
            ->load('planFeatures.feature'));
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Plan $plan, array $data): Plan
    {
        $this->access->ensureCanManage($user);
        $data = $this->normalizePricing([
            ...$plan->only([
                'price_amount',
                'currency_code',
                'billing_interval',
                'is_custom_pricing',
            ]),
            ...$data,
        ]);

        return DB::transaction(function () use ($plan, $data): Plan {
            $plan->fill($data)->save();

            return $plan->refresh()->load('planFeatures.feature');
        });
    }

    public function delete(User $user, Plan $plan): void
    {
        $this->access->ensureCanManage($user);

        if (DB::table('stores')->where('plan_id', $plan->getKey())->exists()) {
            throw ValidationException::withMessages([
                'plan' => ['A plan assigned to a Store cannot be removed. Archive it instead.'],
            ]);
        }

        $plan->delete();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalizePricing(array $data): array
    {
        if ((bool) ($data['is_custom_pricing'] ?? false)) {
            $data['price_amount'] = null;
            $data['billing_interval'] = null;

            return $data;
        }

        if (! isset($data['price_amount']) || ! isset($data['billing_interval'])) {
            throw ValidationException::withMessages([
                'price_amount' => ['A fixed-price plan requires a price and billing interval.'],
            ]);
        }

        return $data;
    }
}
