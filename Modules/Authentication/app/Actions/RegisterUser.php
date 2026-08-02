<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Data\RegistrationResult;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreProvisioner;

final readonly class RegisterUser
{
    public function __construct(private StoreProvisioner $storeProvisioner) {}

    /** @param array{name: string, email: string, password: string, store_name: string, store_slug: string, theme_template_key?: string, store_country_code?: string|null, store_state?: string|null, store_city?: string|null, store_zip?: string|null, store_address_1?: string|null, store_address_2?: string|null} $data */
    public function handle(array $data): RegistrationResult
    {
        return DB::transaction(function () use ($data): RegistrationResult {
            $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'scope' => AccessScope::Store]);
            $store = $this->storeProvisioner->provision($user, $data['store_name'], $data['store_slug'], [
                'contact_email' => $user->email,
                'theme_template_key' => $data['theme_template_key'] ?? config('stores.default_theme_key', 'default'),
                'store_country_code' => $data['store_country_code'] ?? null,
                'store_state' => $data['store_state'] ?? null,
                'store_city' => $data['store_city'] ?? null,
                'store_zip' => $data['store_zip'] ?? null,
                'store_address_1' => $data['store_address_1'] ?? null,
                'store_address_2' => $data['store_address_2'] ?? null,
            ]);
            DB::afterCommit(function () use ($user): void {
                event(new Registered($user));
                $user->sendEmailVerificationNotification();
            });

            return new RegistrationResult($user, $store);
        });
    }
}
