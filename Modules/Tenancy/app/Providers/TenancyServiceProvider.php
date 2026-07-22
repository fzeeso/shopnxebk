<?php

declare(strict_types=1);

namespace Modules\Tenancy\Providers;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Modules\Tenancy\Actions\ProvisionTenant;
use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Contracts\TenantProvisioner;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Policies\TenantPolicy;
use Modules\Tenancy\TenantContext\RequestTenantContext;
use Spatie\Multitenancy\Contracts\IsTenant;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'tenancy');
        $this->app->scoped(TenantContext::class, RequestTenantContext::class);
        $this->app->bind(TenantProvisioner::class, ProvisionTenant::class);
        $this->app->bind(IsTenant::class, Tenant::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        Gate::policy(Tenant::class, TenantPolicy::class);

        Queue::before(function (JobProcessing $event): void {
            $tenant = Tenant::current();
            if ($tenant !== null) {
                app(TenantContext::class)->set($tenant);
                setPermissionsTeamId($tenant->getKey());
            }
        });
        $clear = function (JobProcessed|JobExceptionOccurred $event): void {
            app(TenantContext::class)->clear();
            Tenant::forgetCurrent();
            setPermissionsTeamId(null);
        };
        Queue::after($clear);
        Queue::exceptionOccurred($clear);
    }
}
