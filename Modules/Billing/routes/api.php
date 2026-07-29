<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Api\V1\PlatformFeatureController;
use Modules\Billing\Http\Controllers\Api\V1\PlatformPlanController;
use Modules\Billing\Http\Controllers\Api\V1\PlatformPlanFeatureController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform')
    ->name('api.v1.platform.')
    ->group(function (): void {
        Route::get('plans', [PlatformPlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [PlatformPlanController::class, 'store'])->name('plans.store');
        Route::get('plans/{plan}', [PlatformPlanController::class, 'show'])->name('plans.show');
        Route::patch('plans/{plan}', [PlatformPlanController::class, 'update'])->name('plans.update');
        Route::delete('plans/{plan}', [PlatformPlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('features', [PlatformFeatureController::class, 'index'])->name('features.index');
        Route::post('features', [PlatformFeatureController::class, 'store'])->name('features.store');
        Route::patch('features/{feature}', [PlatformFeatureController::class, 'update'])->name('features.update');
        Route::delete('features/{feature}', [PlatformFeatureController::class, 'destroy'])->name('features.destroy');

        Route::put('plans/{plan}/features/{feature}', [PlatformPlanFeatureController::class, 'upsert'])
            ->name('plans.features.upsert');
        Route::delete('plans/{plan}/features/{feature}', [PlatformPlanFeatureController::class, 'destroy'])
            ->name('plans.features.destroy');
    });
