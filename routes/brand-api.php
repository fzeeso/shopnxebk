<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\BrandController;

Route::get('api/v1/store/brands/{brand}/media/{collection}', [BrandController::class, 'media'])
    ->middleware('signed:relative')
    ->name('api.v1.store.brands.media');

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::apiResource('brands', BrandController::class);
    });
