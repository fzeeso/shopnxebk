<?php

declare(strict_types=1);

namespace Modules\Authentication\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Modules\Authentication\Support\TotpProvider;

final class AuthenticationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'authentication');
        $this->app->singleton(TwoFactorAuthenticationProvider::class, TotpProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
