<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\ModifierLibraryCategoryController;
use Modules\Catalog\Http\Controllers\Api\V1\ModifierLibraryController;
use Modules\Catalog\Http\Controllers\Api\V1\ModifierValueController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductModifierController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductModifierGroupController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('modifier-library/categories', [ModifierLibraryCategoryController::class, 'index'])->name('modifier-library.categories.index');
        Route::post('modifier-library/categories', [ModifierLibraryCategoryController::class, 'store'])->name('modifier-library.categories.store');
        Route::get('modifier-library/categories/{category}', [ModifierLibraryCategoryController::class, 'show'])->name('modifier-library.categories.show');
        Route::patch('modifier-library/categories/{category}', [ModifierLibraryCategoryController::class, 'update'])->name('modifier-library.categories.update');
        Route::delete('modifier-library/categories/{category}', [ModifierLibraryCategoryController::class, 'destroy'])->name('modifier-library.categories.destroy');
        Route::get('modifier-library', [ModifierLibraryController::class, 'index'])->name('modifier-library.index');
        Route::post('modifier-library', [ModifierLibraryController::class, 'store'])->name('modifier-library.store');
        Route::get('modifier-library/{modifier}', [ModifierLibraryController::class, 'show'])->name('modifier-library.show');
        Route::patch('modifier-library/{modifier}', [ModifierLibraryController::class, 'update'])->name('modifier-library.update');
        Route::patch('modifier-library/{modifier}/active', [ModifierLibraryController::class, 'active'])->name('modifier-library.active');
        Route::put('modifier-library/{modifier}/translations', [ModifierLibraryController::class, 'replaceTranslations'])->name('modifier-library.translations.replace');
        Route::put('modifier-library/{modifier}/values', [ModifierLibraryController::class, 'replaceValues'])->name('modifier-library.values.replace');
        Route::get('modifier-library/{modifier}/values', [ModifierValueController::class, 'index'])->name('modifier-library.values.index');
        Route::post('modifier-library/{modifier}/values', [ModifierValueController::class, 'store'])->name('modifier-library.values.store');
        Route::get('modifier-library/{modifier}/values/{value}', [ModifierValueController::class, 'show'])->name('modifier-library.values.show');
        Route::patch('modifier-library/{modifier}/values/{value}', [ModifierValueController::class, 'update'])->name('modifier-library.values.update');
        Route::delete('modifier-library/{modifier}/values/{value}', [ModifierValueController::class, 'destroy'])->name('modifier-library.values.destroy');
        Route::put('modifier-library/{modifier}/validation-rules', [ModifierLibraryController::class, 'replaceValidationRules'])->name('modifier-library.validation-rules.replace');
        Route::put('modifier-library/{modifier}/price-adjustments', [ModifierLibraryController::class, 'replacePriceAdjustments'])->name('modifier-library.price-adjustments.replace');
        Route::delete('modifier-library/{modifier}', [ModifierLibraryController::class, 'destroy'])->name('modifier-library.destroy');

        Route::get('products/{product}/modifier-groups', [ProductModifierGroupController::class, 'index'])->name('products.modifier-groups.index');
        Route::post('products/{product}/modifier-groups', [ProductModifierGroupController::class, 'store'])->name('products.modifier-groups.store');
        Route::get('products/{product}/modifier-groups/{group}', [ProductModifierGroupController::class, 'show'])->name('products.modifier-groups.show');
        Route::patch('products/{product}/modifier-groups/{group}', [ProductModifierGroupController::class, 'update'])->name('products.modifier-groups.update');
        Route::delete('products/{product}/modifier-groups/{group}', [ProductModifierGroupController::class, 'destroy'])->name('products.modifier-groups.destroy');
        Route::get('products/{product}/modifiers/resolved', [ProductModifierController::class, 'resolved'])->name('products.modifiers.resolved');
        Route::patch('products/{product}/modifiers/reorder', [ProductModifierController::class, 'reorder'])->name('products.modifiers.reorder');
        Route::get('products/{product}/modifiers', [ProductModifierController::class, 'index'])->name('products.modifiers.index');
        Route::post('products/{product}/modifiers', [ProductModifierController::class, 'store'])->name('products.modifiers.store');
        Route::get('products/{product}/modifiers/{assignment}', [ProductModifierController::class, 'show'])->name('products.modifiers.show');
        Route::patch('products/{product}/modifiers/{assignment}', [ProductModifierController::class, 'update'])->name('products.modifiers.update');
        Route::put('products/{product}/modifiers/{assignment}/translations', [ProductModifierController::class, 'replaceTranslations'])->name('products.modifiers.translations.replace');
        Route::put('products/{product}/modifiers/{assignment}/value-assignments', [ProductModifierController::class, 'replaceValues'])->name('products.modifiers.value-assignments.replace');
        Route::put('products/{product}/modifiers/{assignment}/price-overrides', [ProductModifierController::class, 'replacePriceOverrides'])->name('products.modifiers.price-overrides.replace');
        Route::put('products/{product}/modifiers/{assignment}/value-price-overrides', [ProductModifierController::class, 'replaceValuePriceOverrides'])->name('products.modifiers.value-price-overrides.replace');
        Route::delete('products/{product}/modifiers/{assignment}', [ProductModifierController::class, 'destroy'])->name('products.modifiers.destroy');
    });
