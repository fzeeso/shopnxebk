<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\CustomFieldDefinitionController;
use Modules\Catalog\Http\Controllers\Api\V1\CustomFieldOptionController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductCustomFieldValueController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('custom-fields', [CustomFieldDefinitionController::class, 'index'])
            ->name('custom-fields.index');
        Route::post('custom-fields', [CustomFieldDefinitionController::class, 'store'])
            ->name('custom-fields.store');
        Route::get('custom-fields/{definition}', [CustomFieldDefinitionController::class, 'show'])
            ->name('custom-fields.show');
        Route::patch('custom-fields/{definition}', [CustomFieldDefinitionController::class, 'update'])
            ->name('custom-fields.update');
        Route::delete('custom-fields/{definition}', [CustomFieldDefinitionController::class, 'destroy'])
            ->name('custom-fields.destroy');
        Route::get('custom-fields/{definition}/options', [CustomFieldOptionController::class, 'index'])
            ->name('custom-fields.options.index');
        Route::post('custom-fields/{definition}/options', [CustomFieldOptionController::class, 'store'])
            ->name('custom-fields.options.store');
        Route::get('custom-fields/{definition}/options/{option}', [CustomFieldOptionController::class, 'show'])
            ->name('custom-fields.options.show');
        Route::patch('custom-fields/{definition}/options/{option}', [CustomFieldOptionController::class, 'update'])
            ->name('custom-fields.options.update');
        Route::delete('custom-fields/{definition}/options/{option}', [CustomFieldOptionController::class, 'destroy'])
            ->name('custom-fields.options.destroy');

        Route::get('products/{product}/custom-field-values', [ProductCustomFieldValueController::class, 'productIndex'])
            ->name('products.custom-field-values.index');
        Route::get('products/{product}/custom-field-values/{definition}', [ProductCustomFieldValueController::class, 'productShow'])
            ->name('products.custom-field-values.show');
        Route::put('products/{product}/custom-field-values/{definition}', [ProductCustomFieldValueController::class, 'productSet'])
            ->name('products.custom-field-values.set');
        Route::delete('products/{product}/custom-field-values/{definition}', [ProductCustomFieldValueController::class, 'productDestroy'])
            ->name('products.custom-field-values.destroy');

        Route::get('products/{product}/variants/{variant}/custom-field-values', [ProductCustomFieldValueController::class, 'variantIndex'])
            ->name('products.variants.custom-field-values.index');
        Route::get('products/{product}/variants/{variant}/custom-field-values/{definition}', [ProductCustomFieldValueController::class, 'variantShow'])
            ->name('products.variants.custom-field-values.show');
        Route::put('products/{product}/variants/{variant}/custom-field-values/{definition}', [ProductCustomFieldValueController::class, 'variantSet'])
            ->name('products.variants.custom-field-values.set');
        Route::delete('products/{product}/variants/{variant}/custom-field-values/{definition}', [ProductCustomFieldValueController::class, 'variantDestroy'])
            ->name('products.variants.custom-field-values.destroy');
    });
