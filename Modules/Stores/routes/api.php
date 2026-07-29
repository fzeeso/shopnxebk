<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Stores\Http\Controllers\Api\V1\PlatformLanguageController;
use Modules\Stores\Http\Controllers\Api\V1\StoreController;
use Modules\Stores\Http\Controllers\Api\V1\StoreLanguageController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store'])
    ->prefix('api/v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::post('stores', [StoreController::class, 'store'])->name('stores.store');

        Route::middleware(['store', 'store.member'])->prefix('store')->name('store.')->group(function (): void {
            Route::get('/', [StoreController::class, 'show'])->name('show');
            Route::patch('profile', [StoreController::class, 'updateProfile'])->name('profile.update');
            Route::get('settings', [StoreController::class, 'settings'])->name('settings.show');
            Route::patch('settings', [StoreController::class, 'updateSettings'])->name('settings.update');
            Route::get('languages', [StoreLanguageController::class, 'index'])->name('languages.index');
            Route::put('languages', [StoreLanguageController::class, 'update'])->name('languages.update');
        });
    });

Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform')
    ->name('api.v1.platform.')
    ->group(function (): void {
        Route::get('languages', [PlatformLanguageController::class, 'index'])->name('languages.index');
        Route::post('languages', [PlatformLanguageController::class, 'store'])->name('languages.store');
    });
