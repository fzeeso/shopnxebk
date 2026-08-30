<?php

declare(strict_types=1);

namespace Modules\Customers\Providers;

use App\Support\Translations\Contracts\TranslationContentHandler;
use Illuminate\Support\ServiceProvider;
use Modules\Customers\Contracts\CatalogTargetResolver;
use Modules\Customers\Contracts\CustomerGroupResolver;
use Modules\Customers\Infrastructure\Catalog\EloquentCatalogTargetResolver;
use Modules\Customers\Services\CustomerGroupReferenceService;
use Modules\Customers\Services\Translations\CustomerGroupTranslationHandler;

final class CustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'customers');
        $this->app->bind(CatalogTargetResolver::class, EloquentCatalogTargetResolver::class);
        $this->app->bind(CustomerGroupResolver::class, CustomerGroupReferenceService::class);
        $this->app->tag([CustomerGroupTranslationHandler::class], TranslationContentHandler::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
