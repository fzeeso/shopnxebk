<?php

declare(strict_types=1);

namespace App\Providers;

use Laravel\Horizon\HorizonServiceProvider as BaseHorizonServiceProvider;

final class HorizonServiceProvider extends BaseHorizonServiceProvider
{
    protected function registerRoutes(): void
    {
        if ((bool) config('observability.internal_dashboards_enabled')) {
            parent::registerRoutes();
        }
    }
}
