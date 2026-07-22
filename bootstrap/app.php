<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\ClearRequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Tenancy\Http\Middleware\EnsureTenantMembership;
use Modules\Tenancy\Http\Middleware\ResolveOptionalTenant;
use Modules\Tenancy\Http\Middleware\ResolveTenant;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api',
        'middleware' => ['api', 'auth:sanctum', 'tenant', 'tenant.member'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(AssignRequestId::class);
        $middleware->append(ClearRequestContext::class);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.member' => EnsureTenantMembership::class,
            'tenant.optional' => ResolveOptionalTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => ! $request->is('horizon*', 'pulse*', 'telescope*'),
        );
        $exceptions->render(fn (AuthenticationException $e, Request $request) => response()->json([
            'message' => 'Unauthenticated.',
            'request_id' => $request->attributes->get('request_id'),
        ], 401));
        $exceptions->render(fn (AuthorizationException $e, Request $request) => response()->json([
            'message' => 'Forbidden.',
            'request_id' => $request->attributes->get('request_id'),
        ], 403));
        $exceptions->render(fn (ValidationException $e, Request $request) => response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $e->errors(),
            'request_id' => $request->attributes->get('request_id'),
        ], 422));
        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => response()->json([
            'message' => 'Not found.',
            'request_id' => $request->attributes->get('request_id'),
        ], 404));
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->expectsJson() && $request->is('horizon*', 'pulse*', 'telescope*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getStatusCode() >= 500 ? 'Server error.' : ($e->getStatusCode() === 404 ? 'Not found.' : ($e->getMessage() ?: 'Request failed.')),
                'request_id' => $request->attributes->get('request_id'),
            ], $e->getStatusCode());
        });
    })->create();
