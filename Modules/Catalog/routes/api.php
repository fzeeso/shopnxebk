<?php

declare(strict_types=1);

use App\Http\Controllers\BrandController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::get('brands/{brand}', [BrandController::class, 'show'])->name('brands.show');
        Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    });
