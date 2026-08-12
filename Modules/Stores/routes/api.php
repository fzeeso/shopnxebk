<?php

declare(strict_types=1);

use App\Http\Controllers\TranslationRequestController;
use Illuminate\Support\Facades\Route;
use Modules\Stores\Http\Controllers\Api\V1\PlatformMerchantController;
use Modules\Stores\Http\Controllers\Api\V1\PlatformStoreController;
use Modules\Stores\Http\Controllers\Api\V1\PlatformStoreDomainController;
use Modules\Stores\Http\Controllers\Api\V1\PlatformStoreLocaleSettingsController;
use Modules\Stores\Http\Controllers\Api\V1\PolicyTypeController;
use Modules\Stores\Http\Controllers\Api\V1\PolicyVersionController;
use Modules\Stores\Http\Controllers\Api\V1\StoreController;
use Modules\Stores\Http\Controllers\Api\V1\StorefrontPolicyController;
use Modules\Stores\Http\Controllers\Api\V1\StoreLanguageController;
use Modules\Stores\Http\Controllers\Api\V1\StorePolicyManagementController;
use Modules\Stores\Http\Controllers\Api\V1\StorePolicyLocaleController;
use Modules\Stores\Http\Controllers\Api\V1\StoreUserController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store'])
    ->prefix('api/v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::post('stores', [StoreController::class, 'store'])->name('stores.store');

        Route::middleware(['store', 'store.member'])->prefix('store')->name('store.')->group(function (): void {
            Route::get('/', [StoreController::class, 'show'])->name('show');
            Route::patch('profile', [StoreController::class, 'updateProfile'])->name('profile.update');
            Route::get('settings', [StoreController::class, 'settings'])->name('settings.show');
            Route::patch('settings', [StoreController::class, 'updateSettings'])->name('settings.update');
            Route::get('languages', [StoreLanguageController::class, 'index'])->name('languages.index');
            Route::put('languages', [StoreLanguageController::class, 'update'])->name('languages.update');
            Route::get('policy-types', [PolicyTypeController::class, 'storeIndex'])->name('policy-types.index');
            Route::get('policies', [StorePolicyManagementController::class, 'index'])->name('policies.index');
            Route::post('policies', [StorePolicyManagementController::class, 'store'])->name('policies.store');
            Route::get('policies/{storePolicy}', [StorePolicyManagementController::class, 'show'])->name('policies.show');
            Route::patch('policies/{storePolicy}', [StorePolicyManagementController::class, 'update'])->name('policies.update');
            Route::post('policies/{storePolicy}/publish', [StorePolicyManagementController::class, 'publish'])->name('policies.publish');
            Route::post('policies/{storePolicy}/unpublish', [StorePolicyManagementController::class, 'unpublish'])->name('policies.unpublish');
            Route::post('policies/{storePolicy}/enable', [StorePolicyManagementController::class, 'enable'])->name('policies.enable');
            Route::post('policies/{storePolicy}/disable', [StorePolicyManagementController::class, 'disable'])->name('policies.disable');
            Route::put('policies/{storePolicy}/translations/{language}', [StorePolicyLocaleController::class, 'upsert'])->name('policies.translations.upsert');
            Route::delete('policies/{storePolicy}/translations/{language}', [StorePolicyLocaleController::class, 'destroy'])->name('policies.translations.destroy');
            Route::get('translation-requests/{translationRequest}', [TranslationRequestController::class, 'show'])->name('translation-requests.show');
            Route::get('policies/{storePolicy}/versions', [PolicyVersionController::class, 'index'])->name('policies.versions.index');
            Route::post('policies/{storePolicy}/versions/{policyVersion}/restore', [PolicyVersionController::class, 'restore'])->name('policies.versions.restore');
            Route::delete('policies/{storePolicy}', [StorePolicyManagementController::class, 'destroy'])->name('policies.destroy');
            Route::get('roles', [StoreUserController::class, 'roles'])->name('roles.index');
            Route::get('users', [StoreUserController::class, 'index'])->name('users.index');
            Route::post('users', [StoreUserController::class, 'store'])->name('users.store');
        });
    });

Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform')
    ->name('api.v1.platform.')
    ->group(function (): void {
        Route::get('stores', [PlatformStoreController::class, 'index'])->name('stores.index');
        Route::post('stores', [PlatformStoreController::class, 'store'])->name('stores.store');
        Route::get('stores/{store}/domains', [PlatformStoreDomainController::class, 'index'])->name('stores.domains.index');
        Route::post('stores/{store}/domains', [PlatformStoreDomainController::class, 'store'])->name('stores.domains.store');
        Route::patch('stores/{store}/domains/{domain}', [PlatformStoreDomainController::class, 'update'])->name('stores.domains.update');
        Route::get('stores/{store}/locale-settings', [PlatformStoreLocaleSettingsController::class, 'show'])->name('stores.locale-settings.show');
        Route::patch('stores/{store}/locale-settings', [PlatformStoreLocaleSettingsController::class, 'update'])->name('stores.locale-settings.update');
        Route::get('stores/{store}', [PlatformStoreController::class, 'show'])->name('stores.show');
        Route::patch('stores/{store}', [PlatformStoreController::class, 'update'])->name('stores.update');
        Route::get('merchant-roles', [PlatformMerchantController::class, 'roles'])->name('merchant-roles.index');
        Route::get('merchants', [PlatformMerchantController::class, 'index'])->name('merchants.index');
        Route::post('merchants', [PlatformMerchantController::class, 'store'])->name('merchants.store');
        Route::get('merchants/{merchant}', [PlatformMerchantController::class, 'show'])->name('merchants.show');
        Route::patch('merchants/{merchant}', [PlatformMerchantController::class, 'update'])->name('merchants.update');
        Route::get('policy-types', [PolicyTypeController::class, 'platformIndex'])->name('policy-types.index');
        Route::post('policy-types', [PolicyTypeController::class, 'store'])->name('policy-types.store');
        Route::patch('policy-types/{policyType}', [PolicyTypeController::class, 'update'])->name('policy-types.update');
        Route::delete('policy-types/{policyType}', [PolicyTypeController::class, 'destroy'])->name('policy-types.destroy');
    });

Route::middleware(['api', 'store'])
    ->prefix('api/v1/storefront')
    ->name('api.v1.storefront.')
    ->group(function (): void {
        Route::get('policies', [StorefrontPolicyController::class, 'index'])->name('policies.index');
        Route::get('policies/{slug}', [StorefrontPolicyController::class, 'show'])->name('policies.show');
    });
