<?php

namespace App\Providers;

use App\Models\Media;
use App\Policies\MediaPolicy;
use App\Support\InternalDashboardAccess;
use App\Support\Translations\Contracts\TranslationContentHandler;
use App\Support\Translations\OpenAiTranslationService;
use App\Support\Translations\TranslationContentRegistry;
use App\Support\Translations\TranslationProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Horizon\Horizon;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Sanctum\Sanctum;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Catalog\Services\Translations\BrandTranslationHandler;
use Modules\Catalog\Services\Translations\CategoryTranslationHandler;
use Modules\Catalog\Services\Translations\ProductTranslationHandler;
use Modules\Catalog\Services\Translations\ProductTypeTranslationHandler;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Modules\Stores\Services\Translations\StorePolicyTranslationHandler;
use Modules\Stores\Support\StoreRuntimeDatabaseGuard;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TranslationProvider::class, OpenAiTranslationService::class);
        $this->app->tag([
            BrandTranslationHandler::class,
            CategoryTranslationHandler::class,
            ProductTranslationHandler::class,
            ProductTypeTranslationHandler::class,
            StorePolicyTranslationHandler::class,
        ], TranslationContentHandler::class);
        $this->app->singleton(
            TranslationContentRegistry::class,
            fn ($app): TranslationContentRegistry => new TranslationContentRegistry(
                $app->tagged(TranslationContentHandler::class),
            ),
        );

        $this->mergeConfigFrom(base_path('Modules/Catalog/config/config.php'), 'catalog');

        Fortify::ignoreRoutes();

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
        DB::connection()->beforeExecuting(function (string $query): void {
            $request = $this->app->bound('request') ? $this->app->make('request') : null;
            if ($request instanceof Request) {
                $this->app->make(StoreRuntimeDatabaseGuard::class)->assertAllowed($request, $query);
            }
        });

        $this->loadMigrationsFrom(base_path('Modules/Catalog/database/migrations'));
        $this->loadRoutesFrom(base_path('routes/brand-api.php'));
        $this->loadRoutesFrom(base_path('routes/fulfillment-type-api.php'));
        $this->loadRoutesFrom(base_path('routes/product-api.php'));
        $this->loadRoutesFrom(base_path('routes/shared-product-option-api.php'));
        $this->loadRoutesFrom(base_path('routes/custom-field-api.php'));
        $this->loadRoutesFrom(base_path('routes/modifier-api.php'));

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Sanctum::getAccessTokenFromRequestUsing(static function (Request $request): ?string {
            $token = $request->bearerToken();
            if ($token === null || ! str_contains($token, '|')) {
                return $token;
            }

            [$id, $plainTextToken] = explode('|', $token, 2);

            return $plainTextToken !== '' && (ctype_digit($id) || Str::isUuid($id))
                ? $token
                : null;
        });

        Model::shouldBeStrict($this->app->isLocal() || $this->app->runningUnitTests());

        Gate::before(fn ($user, string $ability): ?bool => $user->isPlatformSuperAdmin() ? true : null);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::define('viewHorizon', fn ($user): bool => InternalDashboardAccess::allows(request(), $user));
        Gate::define('viewPulse', fn ($user): bool => InternalDashboardAccess::allows(request(), $user));
        Gate::define('viewTelescope', fn ($user): bool => InternalDashboardAccess::allows(request(), $user));

        Horizon::auth(fn (Request $request): bool => InternalDashboardAccess::allows($request, $request->user()));
        Pulse::user(fn ($user) => $user->isPlatformSuperAdmin() ? ['name' => $user->name, 'extra' => $user->email] : null);

        RateLimiter::for('auth.login', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('auth.register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('auth.token', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('auth.token-management', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('auth.password-management', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('auth.mfa', fn (Request $request) => Limit::perMinute(5)->by(hash('sha256', (string) $request->input('challenge_token')).'|'.$request->ip()));
        RateLimiter::for('auth.mfa-management', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('graphql', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        ResetPassword::createUrlUsing(fn ($user, string $token): string => rtrim((string) config('app.frontend_reset_password_url'), '/').'?token='.urlencode($token).'&email='.urlencode($user->getEmailForPasswordReset()));

        if (class_exists(RequestTerminated::class)) {
            $this->app['events']->listen(RequestTerminated::class, function (): void {
                app(StoreContext::class)->clear();
                Store::forgetCurrent();
                setPermissionsTeamId(null);
                auth()->forgetGuards();
                app()->setLocale((string) config('app.locale'));
            });
        }
    }
}
