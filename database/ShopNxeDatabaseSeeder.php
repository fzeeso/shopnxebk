<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Actions\EnsureCurrencyCatalog;
use Modules\Stores\Actions\EnsureLanguageCatalog;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(EnsureAuthorizationCatalog::class)->ensure();
        app(EnsureCurrencyCatalog::class)->ensure();
        app(EnsureLanguageCatalog::class)->ensure();

        $email = env('PLATFORM_ADMIN_EMAIL');
        $password = env('PLATFORM_ADMIN_PASSWORD');

        if (app()->environment('local') && is_string($email) && $email !== '' && is_string($password) && $password !== '') {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->fill([
                'name' => 'Platform Administrator',
                'password' => Hash::make($password),
                'scope' => AccessScope::Platform,
            ]);
            $user->forceFill(['email_verified_at' => now()]);
            $user->save();

            app(ScopedRoleAssignmentService::class)->assignPlatformRole($user, 'Super Admin');
        }
    }
}
