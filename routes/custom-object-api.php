<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\CustomObjectEntryController;
use Modules\Catalog\Http\Controllers\Api\V1\CustomObjectFieldController;
use Modules\Catalog\Http\Controllers\Api\V1\CustomObjectReferenceController;
use Modules\Catalog\Http\Controllers\Api\V1\CustomObjectTypeController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('store.')
    ->group(function (): void {
        Route::get('custom-object-types', [CustomObjectTypeController::class, 'index'])
            ->name('custom-object-types.index');
        Route::post('custom-object-types', [CustomObjectTypeController::class, 'store'])
            ->name('custom-object-types.store');
        Route::get('custom-object-types/{type}', [CustomObjectTypeController::class, 'show'])
            ->name('custom-object-types.show');
        Route::patch('custom-object-types/{type}', [CustomObjectTypeController::class, 'update'])
            ->name('custom-object-types.update');
        Route::delete('custom-object-types/{type}', [CustomObjectTypeController::class, 'destroy'])
            ->name('custom-object-types.destroy');

        Route::get('custom-object-types/{type}/fields', [CustomObjectFieldController::class, 'index'])
            ->name('custom-object-fields.index');
        Route::post('custom-object-types/{type}/fields', [CustomObjectFieldController::class, 'store'])
            ->name('custom-object-fields.store');
        Route::get('custom-object-fields/{field}', [CustomObjectFieldController::class, 'show'])
            ->name('custom-object-fields.show');
        Route::patch('custom-object-fields/{field}', [CustomObjectFieldController::class, 'update'])
            ->name('custom-object-fields.update');
        Route::delete('custom-object-fields/{field}', [CustomObjectFieldController::class, 'destroy'])
            ->name('custom-object-fields.destroy');

        Route::get('custom-object-types/{type}/entries', [CustomObjectEntryController::class, 'index'])
            ->name('custom-object-entries.index');
        Route::get('custom-object-types/{type}/entries/options', [CustomObjectEntryController::class, 'options'])
            ->name('custom-object-entries.options');
        Route::post('custom-object-types/{type}/entries', [CustomObjectEntryController::class, 'store'])
            ->name('custom-object-entries.store');
        Route::get('custom-object-entries/{entry}', [CustomObjectEntryController::class, 'show'])
            ->name('custom-object-entries.show');
        Route::patch('custom-object-entries/{entry}', [CustomObjectEntryController::class, 'update'])
            ->name('custom-object-entries.update');
        Route::delete('custom-object-entries/{entry}', [CustomObjectEntryController::class, 'destroy'])
            ->name('custom-object-entries.destroy');

        Route::get('custom-object-references', [CustomObjectReferenceController::class, 'index'])
            ->name('custom-object-references.index');
        Route::put('custom-object-references/{definition}', [CustomObjectReferenceController::class, 'replace'])
            ->name('custom-object-references.replace');
        Route::delete('custom-object-references/{definition}', [CustomObjectReferenceController::class, 'clear'])
            ->name('custom-object-references.clear');

        Route::get('products/{product}/custom-object-references', [CustomObjectReferenceController::class, 'productIndex'])
            ->name('products.custom-object-references.index');
        Route::put('products/{product}/custom-object-references/{definition}', [CustomObjectReferenceController::class, 'productReplace'])
            ->name('products.custom-object-references.replace');
        Route::delete('products/{product}/custom-object-references/{definition}', [CustomObjectReferenceController::class, 'productClear'])
            ->name('products.custom-object-references.clear');
    });
