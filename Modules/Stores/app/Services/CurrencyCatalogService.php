<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Currency;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CurrencyCatalogService
{
    /** @return Collection<int, Currency> */
    public function listPlatform(User $user): Collection
    {
        $this->ensurePlatformUser($user);

        return Currency::query()
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function createPlatform(User $user, array $data): Currency
    {
        $this->ensurePlatformUser($user);
        $hasRate = array_key_exists('usd_exchange_rate', $data)
            && $data['usd_exchange_rate'] !== null;

        return Currency::query()->create([
            ...$data,
            'is_base' => false,
            'is_active' => $data['is_active'] ?? true,
            'exchange_rate_updated_at' => $hasRate ? now() : null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updatePlatform(User $user, Currency $currency, array $data): Currency
    {
        $this->ensurePlatformUser($user);
        $rateWasProvided = array_key_exists('usd_exchange_rate', $data);

        if ($currency->is_base) {
            if (
                (array_key_exists('usd_exchange_rate', $data)
                    && (float) $data['usd_exchange_rate'] !== 1.0)
                || (array_key_exists('is_active', $data) && ! $data['is_active'])
            ) {
                throw ValidationException::withMessages([
                    'usd_exchange_rate' => ['USD is the active base currency and its exchange rate must remain 1.'],
                ]);
            }

            $data['usd_exchange_rate'] = 1;
            $data['is_active'] = true;
        }

        if ($rateWasProvided) {
            $data['exchange_rate_updated_at'] = $data['usd_exchange_rate'] === null
                ? null
                : now();
        }

        $currency->fill($data)->save();

        return $currency->refresh();
    }

    private function ensurePlatformUser(User $user): void
    {
        if (! $user->isPlatformUser()) {
            throw new AccessDeniedHttpException('Platform-scoped account required.');
        }
    }
}
