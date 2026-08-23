<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\FulfillmentType;
use Modules\Settings\Services\PlatformSettingsAccessService;
use Modules\Stores\Contracts\StoreContext;

final readonly class FulfillmentTypeManagementService
{
    public function __construct(
        private PlatformSettingsAccessService $platformAccess,
        private StoreContext $storeContext,
        private CatalogAccessService $catalogAccess,
    ) {}

    /** @return Collection<int, FulfillmentType> */
    public function listPlatform(User $user): Collection
    {
        $this->platformAccess->ensureCanView($user);

        return $this->query()->get();
    }

    /** @return Collection<int, FulfillmentType> */
    public function listStore(User $user): Collection
    {
        $store = $this->storeContext->require();
        $this->catalogAccess->ensureCanView($user, $store);

        return $this->query()->where('is_active', true)->get();
    }

    public function showPlatform(User $user, string $code): FulfillmentType
    {
        $this->platformAccess->ensureCanView($user);

        return $this->query()->where('code', $code)->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function createPlatform(User $user, array $data): FulfillmentType
    {
        $this->platformAccess->ensureCanManage($user);

        return DB::transaction(function () use ($data): FulfillmentType {
            $fulfillmentType = FulfillmentType::query()->create([
                'code' => $data['code'],
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            $fulfillmentType->translations()->createMany($data['translations']);

            return $fulfillmentType->load('translations');
        });
    }

    /** @param array<string, mixed> $data */
    public function updatePlatform(User $user, string $code, array $data): FulfillmentType
    {
        $this->platformAccess->ensureCanManage($user);
        if ($data === []) {
            throw ValidationException::withMessages([
                'input' => ['At least one field must be supplied.'],
            ]);
        }

        return DB::transaction(function () use ($code, $data): FulfillmentType {
            $fulfillmentType = FulfillmentType::query()->where('code', $code)->firstOrFail();
            $fulfillmentType->fill(Arr::only($data, ['is_active', 'sort_order']))->save();

            foreach ($data['translations'] ?? [] as $translation) {
                $fulfillmentType->translations()->updateOrCreate(
                    ['locale' => $translation['locale']],
                    Arr::only($translation, ['name', 'description']),
                );
            }

            return $fulfillmentType->refresh()->load('translations');
        });
    }

    /** @return Builder<FulfillmentType> */
    private function query(): Builder
    {
        return FulfillmentType::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
