<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();
        Telescope::filterBatch(
            /** @param Collection<int, IncomingEntry> $entries */
            static fn (Collection $entries): bool => ! $entries->contains(
                static fn (IncomingEntry $entry): bool => $entry->isRequest()
                    && Str::is(['horizon*', 'pulse*'], ltrim((string) ($entry->content['uri'] ?? ''), '/')),
            ),
        );
        Telescope::filter(fn (IncomingEntry $entry): bool => app()->environment('local'));
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', fn ($user): bool => $user->isPlatformSuperAdmin());
    }

    private function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['password', 'password_confirmation', 'token', 'authorization', 'variables']);
        Telescope::hideRequestHeaders(['authorization', 'cookie', 'x-webhook-signature', 'x-signature']);
    }
}
