<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Authentication\Models\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = env('PLATFORM_ADMIN_EMAIL');
        $password = env('PLATFORM_ADMIN_PASSWORD');

        if (app()->environment('local') && is_string($email) && $email !== '' && is_string($password) && $password !== '') {
            User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => 'Platform Administrator', 'password' => Hash::make($password), 'is_platform_admin' => true, 'email_verified_at' => now()],
            );
        }
    }
}
