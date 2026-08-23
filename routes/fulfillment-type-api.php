<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\FulfillmentTypeController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform/settings')
    ->name('api.v1.platform.settings.')
    ->group(function (): void {
        Route::apiResource('fulfillment-types', FulfillmentTypeController::class)
            ->only(['index', 'store', 'show', 'update']);
    });

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('fulfillment-types', [FulfillmentTypeController::class, 'storeIndex'])
            ->name('fulfillment-types.index');
    });
