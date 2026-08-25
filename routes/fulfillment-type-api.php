<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\FulfillmentTypeController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform/settings')
    ->name('api.v1.platform.settings.')
    ->group(function (): void {
        Route::get('fulfillment-types', [FulfillmentTypeController::class, 'index'])
            ->name('fulfillment-types.index');
        Route::post('fulfillment-types', [FulfillmentTypeController::class, 'store'])
            ->name('fulfillment-types.store');
        Route::get('fulfillment-types/{fulfillmentType}', [FulfillmentTypeController::class, 'show'])
            ->name('fulfillment-types.show');
        Route::patch('fulfillment-types/{fulfillmentType}', [FulfillmentTypeController::class, 'update'])
            ->name('fulfillment-types.update');
    });

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('fulfillment-types', [FulfillmentTypeController::class, 'storeIndex'])
            ->name('fulfillment-types.index');
    });
