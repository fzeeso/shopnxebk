<?php

declare(strict_types=1);

namespace Modules\Themes\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Themes\Contracts\ThemeInstaller;
use Modules\Themes\Services\DefaultThemeInstaller;

final class ThemesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'themes');
        $this->app->bind(ThemeInstaller::class, DefaultThemeInstaller::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
    }
}
