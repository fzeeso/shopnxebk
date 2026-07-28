<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();

        $email = env('PLATFORM_ADMIN_EMAIL');
        $password = env('PLATFORM_ADMIN_PASSWORD');

        if (app()->environment('local') && is_string($email) && $email !== '' && is_string($password) && $password !== '') {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => 'Platform Administrator', 'password' => Hash::make($password), 'email_verified_at' => now()],
            );

            $previousStoreId = getPermissionsTeamId();
            setPermissionsTeamId(null);
            try {
                $user->syncRoles(['Super Admin']);
            } finally {
                setPermissionsTeamId($previousStoreId);
            }
        }
    }
}
