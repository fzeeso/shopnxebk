<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Stores\Contracts\StoreContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class EnsureStoreOwnedBindings
{
    public function __construct(private StoreContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $storeId = (int) $this->context->require()->getKey();
        $route = $request->route();

        foreach ($route?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            $recordStoreId = $parameter->getAttribute('store_id');
            if ($recordStoreId !== null && (int) $recordStoreId !== $storeId) {
                throw new NotFoundHttpException('Not found.');
            }
        }

        return $next($request);
    }
}
