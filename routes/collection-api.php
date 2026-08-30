<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\CollectionController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('collections', [CollectionController::class, 'index'])->name('collections.index');
        Route::post('collections', [CollectionController::class, 'store'])->name('collections.store');
        Route::get('collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
        Route::patch('collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
        Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
        Route::put('collections/{collection}/rules', [CollectionController::class, 'replaceRules'])
            ->name('collections.rules.replace');
        Route::get('collections/{collection}/products', [CollectionController::class, 'products'])
            ->name('collections.products.index');
        Route::put('collections/{collection}/products', [CollectionController::class, 'replaceProducts'])
            ->name('collections.products.replace');
        Route::post('collections/{collection}/refresh', [CollectionController::class, 'refresh'])
            ->name('collections.refresh');
        Route::get('collections/{collection}/ai-jobs', [CollectionController::class, 'aiJobs'])
            ->name('collections.ai-jobs.index');
    });
