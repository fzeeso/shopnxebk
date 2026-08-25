<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\ModifierLibraryCategoryController;
use Modules\Catalog\Http\Controllers\Api\V1\ModifierLibraryController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductModifierController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductModifierGroupController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('modifier-library/categories', [ModifierLibraryCategoryController::class, 'index'])->name('modifier-library.categories.index');
        Route::post('modifier-library/categories', [ModifierLibraryCategoryController::class, 'store'])->name('modifier-library.categories.store');
        Route::patch('modifier-library/categories/{category}', [ModifierLibraryCategoryController::class, 'update'])->name('modifier-library.categories.update');
        Route::delete('modifier-library/categories/{category}', [ModifierLibraryCategoryController::class, 'destroy'])->name('modifier-library.categories.destroy');
        Route::get('modifier-library', [ModifierLibraryController::class, 'index'])->name('modifier-library.index');
        Route::post('modifier-library', [ModifierLibraryController::class, 'store'])->name('modifier-library.store');
        Route::get('modifier-library/{modifier}', [ModifierLibraryController::class, 'show'])->name('modifier-library.show');
        Route::patch('modifier-library/{modifier}', [ModifierLibraryController::class, 'update'])->name('modifier-library.update');
        Route::patch('modifier-library/{modifier}/active', [ModifierLibraryController::class, 'active'])->name('modifier-library.active');
        Route::delete('modifier-library/{modifier}', [ModifierLibraryController::class, 'destroy'])->name('modifier-library.destroy');

        Route::get('products/{product}/modifier-groups', [ProductModifierGroupController::class, 'index'])->name('products.modifier-groups.index');
        Route::post('products/{product}/modifier-groups', [ProductModifierGroupController::class, 'store'])->name('products.modifier-groups.store');
        Route::patch('products/{product}/modifier-groups/{group}', [ProductModifierGroupController::class, 'update'])->name('products.modifier-groups.update');
        Route::delete('products/{product}/modifier-groups/{group}', [ProductModifierGroupController::class, 'destroy'])->name('products.modifier-groups.destroy');
        Route::get('products/{product}/modifiers/resolved', [ProductModifierController::class, 'resolved'])->name('products.modifiers.resolved');
        Route::patch('products/{product}/modifiers/reorder', [ProductModifierController::class, 'reorder'])->name('products.modifiers.reorder');
        Route::get('products/{product}/modifiers', [ProductModifierController::class, 'index'])->name('products.modifiers.index');
        Route::post('products/{product}/modifiers', [ProductModifierController::class, 'store'])->name('products.modifiers.store');
        Route::patch('products/{product}/modifiers/{assignment}', [ProductModifierController::class, 'update'])->name('products.modifiers.update');
        Route::delete('products/{product}/modifiers/{assignment}', [ProductModifierController::class, 'destroy'])->name('products.modifiers.destroy');
    });
