<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Api\V1\CurrencyController;
use Modules\Settings\Http\Controllers\Api\V1\LanguageController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform/settings')
    ->name('api.v1.platform.settings.')
    ->group(function (): void {
        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::post('currencies', [CurrencyController::class, 'store'])->name('currencies.store');
        Route::patch('currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
        Route::get('languages', [LanguageController::class, 'index'])->name('languages.index');
        Route::post('languages', [LanguageController::class, 'store'])->name('languages.store');
        Route::patch('languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
    });

// Backward-compatible aliases for clients created before the Settings component.
Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform')
    ->name('api.v1.platform.')
    ->group(function (): void {
        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::post('currencies', [CurrencyController::class, 'store'])->name('currencies.store');
        Route::patch('currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
        Route::get('languages', [LanguageController::class, 'index'])->name('languages.index');
        Route::post('languages', [LanguageController::class, 'store'])->name('languages.store');
        Route::patch('languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
    });
