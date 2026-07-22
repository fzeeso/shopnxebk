<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Data\RegistrationResult;
use Modules\Authentication\Models\User;
use Modules\Tenancy\Contracts\TenantProvisioner;

final readonly class RegisterUser
{
    public function __construct(private TenantProvisioner $tenantProvisioner) {}

    /** @param array{name: string, email: string, password: string, tenant_name: string, tenant_slug: string} $data */
    public function handle(array $data): RegistrationResult
    {
        return DB::transaction(function () use ($data): RegistrationResult {
            $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $tenant = $this->tenantProvisioner->provision($user, $data['tenant_name'], $data['tenant_slug']);
            DB::afterCommit(function () use ($user): void {
                event(new Registered($user));
                $user->sendEmailVerificationNotification();
            });

            return new RegistrationResult($user, $tenant);
        });
    }
}
