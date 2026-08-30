<?php

declare(strict_types=1);

namespace Modules\Customers\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerCredit;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class CustomerCreditService
{
    public function __construct(
        private StoreContext $context,
        private CustomerAccessService $access,
    ) {}

    /** @return LengthAwarePaginator<int, CustomerCredit> */
    public function list(User $user, Customer $customer, int $perPage): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $this->ensureOwned($customer, $store);

        return CustomerCredit::query()
            ->where('store_id', $store->getKey())
            ->where('customer_id', $customer->getKey())
            ->with('createdBy')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, Customer $customer, array $data): CustomerCredit
    {
        $store = $this->store($user, true);
        $this->ensureOwned($customer, $store);

        return DB::transaction(function () use ($customer, $data, $store, $user): CustomerCredit {
            Customer::query()
                ->where('store_id', $store->getKey())
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return CustomerCredit::query()->create([
                'store_id' => $store->getKey(),
                'customer_id' => $customer->getKey(),
                'amount' => $data['amount'],
                'type' => $data['type'],
                'external_reference' => $data['external_reference'] ?? null,
                'created_by' => $user->getKey(),
                'reason' => trim((string) $data['reason']),
                'occurred_at' => $data['occurred_at'] ?? now(),
            ])->load('createdBy');
        });
    }

    private function ensureOwned(Customer $customer, Store $store): void
    {
        if ((int) $customer->store_id !== (int) $store->getKey()) {
            abort(404);
        }
    }

    private function store(User $user, bool $write): Store
    {
        $store = $this->context->require();
        $write ? $this->access->ensureCanManage($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }
}
