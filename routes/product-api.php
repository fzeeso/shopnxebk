<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\ProductController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductImageController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('products/{product}/images', [ProductImageController::class, 'index'])
            ->name('products.images.index');
        Route::post('products/{product}/images', [ProductImageController::class, 'store'])
            ->name('products.images.store');
        Route::get('products/{product}/images/{image}', [ProductImageController::class, 'show'])
            ->name('products.images.show');
        Route::patch('products/{product}/images/{image}', [ProductImageController::class, 'update'])
            ->name('products.images.update');
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
            ->name('products.images.destroy');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
