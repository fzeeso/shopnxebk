<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\ClearRequestContext;
use App\Http\Middleware\RecordRequestPerformance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Http\Middleware\EnsureUserScope;
use Modules\Stores\Http\Middleware\EnsureStoreMembership;
use Modules\Stores\Http\Middleware\EnsureStoreOwnedBindings;
use Modules\Stores\Http\Middleware\ResolveOptionalStore;
use Modules\Stores\Http\Middleware\ResolveStore;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api',
        'middleware' => ['api', 'auth:sanctum', 'store', 'store.member'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(AssignRequestId::class);
        $middleware->append(RecordRequestPerformance::class);
        $middleware->append(ClearRequestContext::class);
        $middleware->alias([
            'store' => ResolveStore::class,
            'store.member' => EnsureStoreMembership::class,
            'store.bindings' => EnsureStoreOwnedBindings::class,
            'store.optional' => ResolveOptionalStore::class,
            'user.scope' => EnsureUserScope::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveStore::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureStoreMembership::class);
        $middleware->appendToPriorityList(SubstituteBindings::class, EnsureStoreOwnedBindings::class);
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
