<?php

namespace App\Providers;

use App\Support\InternalDashboardAccess;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Sanctum\Sanctum;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Models\Tenant;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! (bool) config('observability.internal_dashboards_enabled')) {
            Pulse::ignoreRoutes();
        }

        if ($this->app->environment('local') && (bool) config('observability.telescope_enabled')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Model::shouldBeStrict($this->app->isLocal() || $this->app->runningUnitTests());

        Gate::before(fn ($user, string $ability): ?bool => $user->is_platform_admin ? true : null);
        Gate::define('viewHorizon', fn ($user): bool => InternalDashboardAccess::allows(request(), $user));
        Gate::define('viewPulse', fn ($user): bool => InternalDashboardAccess::allows(request(), $user));
        Gate::define('viewTelescope', fn ($user): bool => InternalDashboardAccess::allows(request(), $user));

        Horizon::auth(fn (Request $request): bool => InternalDashboardAccess::allows($request, $request->user()));
        Pulse::user(fn ($user) => $user->is_platform_admin ? ['name' => $user->name, 'extra' => $user->email] : null);

        RateLimiter::for('auth.login', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('auth.token', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('graphql', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        ResetPassword::createUrlUsing(fn ($user, string $token): string => rtrim((string) config('app.frontend_reset_password_url'), '/').'?token='.urlencode($token).'&email='.urlencode($user->getEmailForPasswordReset()));

        if (class_exists(RequestTerminated::class)) {
            $this->app['events']->listen(RequestTerminated::class, function (): void {
                app(TenantContext::class)->clear();
                Tenant::forgetCurrent();
                setPermissionsTeamId(null);
                auth()->forgetGuards();
                app()->setLocale((string) config('app.locale'));
            });
        }
    }
}
