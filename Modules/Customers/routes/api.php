<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\Api\V1\CustomerController;
use Modules\Customers\Http\Controllers\Api\V1\CustomerCreditController;
use Modules\Customers\Http\Controllers\Api\V1\CustomerGroupController;
use Modules\Customers\Http\Controllers\Api\V1\CustomerGroupDiscountController;
use Modules\Customers\Http\Controllers\Api\V1\CustomerGroupTranslationController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::get('customers/{customer}/credits', [CustomerCreditController::class, 'index'])->name('customers.credits.index');
        Route::post('customers/{customer}/credits', [CustomerCreditController::class, 'store'])->name('customers.credits.store');

        Route::get('customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
        Route::post('customer-groups', [CustomerGroupController::class, 'store'])->name('customer-groups.store');
        Route::get('customer-groups/{customerGroup}', [CustomerGroupController::class, 'show'])->name('customer-groups.show');
        Route::patch('customer-groups/{customerGroup}', [CustomerGroupController::class, 'update'])->name('customer-groups.update');
        Route::delete('customer-groups/{customerGroup}', [CustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');
        Route::put('customer-groups/{customerGroup}/categories', [CustomerGroupController::class, 'replaceCategories'])->name('customer-groups.categories.replace');
        Route::put('customer-groups/{customerGroup}/translations/{language}', [CustomerGroupTranslationController::class, 'upsert'])->name('customer-groups.translations.upsert');
        Route::delete('customer-groups/{customerGroup}/translations/{language}', [CustomerGroupTranslationController::class, 'destroy'])->name('customer-groups.translations.destroy');
        Route::post('customer-groups/{customerGroup}/discounts', [CustomerGroupDiscountController::class, 'store'])->name('customer-groups.discounts.store');
        Route::put('customer-groups/{customerGroup}/discounts/{discount}', [CustomerGroupDiscountController::class, 'update'])->name('customer-groups.discounts.update');
        Route::delete('customer-groups/{customerGroup}/discounts/{discount}', [CustomerGroupDiscountController::class, 'destroy'])->name('customer-groups.discounts.destroy');
    });
