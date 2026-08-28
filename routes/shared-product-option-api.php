<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\ProductSharedOptionAssignmentController;
use Modules\Catalog\Http\Controllers\Api\V1\SharedProductOptionController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('options', [SharedProductOptionController::class, 'index'])->name('options.index');
        Route::post('options', [SharedProductOptionController::class, 'store'])->name('options.store');
        Route::get('options/{option}', [SharedProductOptionController::class, 'show'])->name('options.show');
        Route::patch('options/{option}', [SharedProductOptionController::class, 'update'])->name('options.update');
        Route::delete('options/{option}', [SharedProductOptionController::class, 'destroy'])->name('options.destroy');

        Route::get('products/{product}/shared-options', [ProductSharedOptionAssignmentController::class, 'index'])
            ->name('products.shared-options.index');
        Route::post('products/{product}/shared-options', [ProductSharedOptionAssignmentController::class, 'store'])
            ->name('products.shared-options.store');
        Route::delete('products/{product}/shared-options/{assignment}', [ProductSharedOptionAssignmentController::class, 'destroy'])
            ->name('products.shared-options.destroy');
    });
