<?php

declare(strict_types=1);

namespace Modules\Customers\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Customers\Contracts\CustomerGroupResolver;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class CustomerManagementService
{
    public function __construct(
        private StoreContext $context,
        private CustomerAccessService $access,
        private CustomerGroupResolver $groups,
    ) {}

    /** @param array<string, mixed> $filters @return LengthAwarePaginator<int, Customer> */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $query = Customer::query()
            ->where('store_id', $store->getKey())
            ->with('group.translations.language')
            ->withSum('credits', 'amount');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['customer_group_id'])) {
            $group = $this->groups->resolve($store, (string) $filters['customer_group_id']);
            $query->where('customer_group_id', $group->id);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('email', 'ILIKE', "%{$search}%")
                    ->orWhere('first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('company', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 25),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1),
            );
    }

    public function show(User $user, Customer $customer): Customer
    {
        $store = $this->store($user, false);
        $this->ensureOwned($customer, $store);

        return $this->load($customer);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Customer
    {
        $store = $this->store($user, true);
        $email = $this->normalizeEmail((string) $data['email']);
        $this->ensureUniqueEmail($store, $email);

        return DB::transaction(function () use ($data, $email, $store): Customer {
            $groupId = $this->groupId($store, $data, true);
            $customer = Customer::query()->create([
                'store_id' => $store->getKey(),
                'customer_group_id' => $groupId,
                'email' => $email,
                'company' => $data['company'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'] ?? CustomerStatus::Active,
                'password' => $data['password'] ?? null,
                'registered_ip' => $data['registered_ip'] ?? null,
                'admin_notes' => $data['admin_notes'] ?? null,
                'points_balance' => $data['points_balance'] ?? 0,
                'redeemed_points' => $data['redeemed_points'] ?? 0,
                'joined_at' => $data['joined_at'] ?? now(),
            ]);

            return $this->load($customer);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Customer $customer, array $data): Customer
    {
        $store = $this->store($user, true);
        $this->ensureOwned($customer, $store);
        if ($data === []) {
            throw ValidationException::withMessages(['customer' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($customer, $data, $store): Customer {
            $attributes = [];
            foreach ([
                'company',
                'first_name',
                'last_name',
                'phone',
                'status',
                'registered_ip',
                'admin_notes',
                'points_balance',
                'redeemed_points',
                'joined_at',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }
            if (array_key_exists('email', $data)) {
                $email = $this->normalizeEmail((string) $data['email']);
                $this->ensureUniqueEmail($store, $email, $customer);
                $attributes['email'] = $email;
            }
            if (array_key_exists('customer_group_id', $data)) {
                $attributes['customer_group_id'] = $this->groupId($store, $data, false);
            }

            $customer->fill($attributes)->save();

            return $this->load($customer->refresh());
        });
    }

    public function delete(User $user, Customer $customer): void
    {
        $store = $this->store($user, true);
        $this->ensureOwned($customer, $store);

        $customer->forceFill(['status' => CustomerStatus::Disabled])->save();
        $customer->delete();
    }

    private function load(Customer $customer): Customer
    {
        return $customer
            ->load('group.translations.language')
            ->loadSum('credits', 'amount');
    }

    /** @param array<string, mixed> $data */
    private function groupId(Store $store, array $data, bool $useDefault): ?int
    {
        $publicId = $data['customer_group_id'] ?? null;
        if ($publicId !== null) {
            return $this->groups->resolve($store, (string) $publicId)->id;
        }
        if (! $useDefault || array_key_exists('customer_group_id', $data)) {
            return null;
        }

        $id = CustomerGroup::query()
            ->where('store_id', $store->getKey())
            ->where('is_default', true)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function ensureUniqueEmail(Store $store, string $email, ?Customer $except = null): void
    {
        if (Customer::query()
            ->where('store_id', $store->getKey())
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists()) {
            throw ValidationException::withMessages(['email' => ['This email is already used by another customer in the Store.']]);
        }
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
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
