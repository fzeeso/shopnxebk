<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Billing\Models\Feature;

final readonly class FeatureAdminService
{
    public function __construct(private PlatformPlanAccessService $access) {}

    /** @return LengthAwarePaginator<int, Feature> */
    public function list(User $user, int $perPage = 25): LengthAwarePaginator
    {
        $this->access->ensureCanManage($user);

        return Feature::query()->orderBy('name')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Feature
    {
        $this->access->ensureCanManage($user);

        return Feature::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Feature $feature, array $data): Feature
    {
        $this->access->ensureCanManage($user);
        $feature->fill($data)->save();

        return $feature->refresh();
    }

    public function delete(User $user, Feature $feature): void
    {
        $this->access->ensureCanManage($user);

        if ($feature->planFeatures()->exists()) {
            throw ValidationException::withMessages([
                'feature' => ['Detach this feature from every plan before removing it.'],
            ]);
        }

        $feature->delete();
    }
}
