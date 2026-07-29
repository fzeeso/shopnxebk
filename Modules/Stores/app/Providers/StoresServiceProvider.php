<?php

declare(strict_types=1);

namespace Modules\Stores\Providers;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Modules\Stores\Actions\ProvisionStore;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Modules\Stores\Policies\StorePolicy;
use Modules\Stores\StoreContext\RequestStoreContext;
use Spatie\Multitenancy\Contracts\IsTenant;

final class StoresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'stores');
        $this->app->scoped(StoreContext::class, RequestStoreContext::class);
        $this->app->bind(StoreProvisioner::class, ProvisionStore::class);
        $this->app->bind(IsTenant::class, Store::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        Gate::policy(Store::class, StorePolicy::class);

        Queue::before(function (JobProcessing $event): void {
            $store = Store::current();
            if ($store !== null) {
                app(StoreContext::class)->set($store);
                setPermissionsTeamId($store->getKey());
            }
        });
        $clear = function (JobProcessed|JobExceptionOccurred $event): void {
            app(StoreContext::class)->clear();
            Store::forgetCurrent();
            setPermissionsTeamId(null);
        };
        Queue::after($clear);
        Queue::exceptionOccurred($clear);
    }
}
