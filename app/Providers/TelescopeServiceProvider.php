<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        Telescope::ignoreRequest('horizon*');
        Telescope::ignoreRequest('pulse*');

        $this->hideSensitiveRequestDetails();
        Telescope::filter(fn (IncomingEntry $entry): bool => app()->environment('local'));
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', fn ($user): bool => $user->is_platform_admin === true);
    }

    private function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['password', 'password_confirmation', 'token', 'authorization', 'variables']);
        Telescope::hideRequestHeaders(['authorization', 'cookie', 'x-webhook-signature', 'x-signature']);
    }
}
