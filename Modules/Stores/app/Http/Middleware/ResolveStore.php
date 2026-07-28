<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Modules\Stores\StoreFinder\HeaderStoreFinder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ResolveStore
{
    public function __construct(private HeaderStoreFinder $finder, private StoreContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        if (! $request->hasHeader('X-Store-ID')) {
            if ($mode === 'optional') {
                return $next($request);
            }
            throw new BadRequestHttpException('X-Store-ID is required for this operation.');
        }

        $store = $this->finder->findForRequest($request);
        if (! $store instanceof Store) {
            throw new NotFoundHttpException('Store not found.');
        }

        $store->makeCurrent();
        $this->context->set($store);
        $request->attributes->set('store', $store);

        return $next($request);
    }
}
