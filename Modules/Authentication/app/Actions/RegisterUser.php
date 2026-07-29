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

    /** @param array{name: string, email: string, password: string, store_name: string, store_slug: string} $data */
    public function handle(array $data): RegistrationResult
    {
        return DB::transaction(function () use ($data): RegistrationResult {
            $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'scope' => AccessScope::Store]);
            $store = $this->storeProvisioner->provision($user, $data['store_name'], $data['store_slug']);
            DB::afterCommit(function () use ($user): void {
                event(new Registered($user));
                $user->sendEmailVerificationNotification();
            });

            return new RegistrationResult($user, $store);
        });
    }
}
