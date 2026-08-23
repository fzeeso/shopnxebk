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
    });
