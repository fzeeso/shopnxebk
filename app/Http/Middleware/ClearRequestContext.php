<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpFoundation\Response;

final readonly class ClearRequestContext
{
    public function __construct(private StoreContext $storeContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            $this->storeContext->clear();
            Store::forgetCurrent();
            setPermissionsTeamId(null);
            Auth::forgetGuards();
            app()->setLocale((string) config('app.locale'));
            Log::withoutContext();
        }
    }
}
