<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Themes\Http\Controllers\Api\V1\PlatformThemeController;
use Modules\Themes\Http\Controllers\Api\V1\PlatformThemeReleaseController;
use Modules\Themes\Http\Controllers\Api\V1\PlatformThemeTaxonomyController;
use Modules\Themes\Http\Controllers\Api\V1\StoreThemeController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform')
    ->name('api.v1.platform.')
    ->group(function (): void {
        Route::get('theme-publishers', [PlatformThemeTaxonomyController::class, 'publishers'])->name('theme-publishers.index');
        Route::post('theme-publishers', [PlatformThemeTaxonomyController::class, 'storePublisher'])->name('theme-publishers.store');
        Route::patch('theme-publishers/{themePublisher}', [PlatformThemeTaxonomyController::class, 'updatePublisher'])->name('theme-publishers.update');
        Route::get('theme-categories', [PlatformThemeTaxonomyController::class, 'categories'])->name('theme-categories.index');
        Route::post('theme-categories', [PlatformThemeTaxonomyController::class, 'storeCategory'])->name('theme-categories.store');
        Route::patch('theme-categories/{themeCategory}', [PlatformThemeTaxonomyController::class, 'updateCategory'])->name('theme-categories.update');

        Route::get('themes', [PlatformThemeController::class, 'index'])->name('themes.index');
        Route::post('themes', [PlatformThemeController::class, 'store'])->name('themes.store');
        Route::get('themes/{theme}', [PlatformThemeController::class, 'show'])->name('themes.show');
        Route::patch('themes/{theme}', [PlatformThemeController::class, 'update'])->name('themes.update');
        Route::post('themes/{theme}/versions', [PlatformThemeReleaseController::class, 'addVersion'])->name('themes.versions.store');
        Route::post('theme-versions/{themeVersion}/submit', [PlatformThemeReleaseController::class, 'submit'])->name('theme-versions.submit');
        Route::post('theme-versions/{themeVersion}/publish', [PlatformThemeReleaseController::class, 'publish'])->name('theme-versions.publish');
        Route::patch('theme-submissions/{themeSubmission}/review', [PlatformThemeReleaseController::class, 'review'])->name('theme-submissions.review');
        Route::post('themes/{theme}/licenses', [PlatformThemeReleaseController::class, 'issueLicense'])->name('themes.licenses.store');
        Route::patch('theme-licenses/{themeLicense}', [PlatformThemeReleaseController::class, 'updateLicense'])->name('theme-licenses.update');
    });

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::get('theme-marketplace', [StoreThemeController::class, 'marketplace'])->name('theme-marketplace.index');
        Route::get('themes', [StoreThemeController::class, 'index'])->name('themes.index');
        Route::post('themes', [StoreThemeController::class, 'install'])->name('themes.install');
        Route::patch('themes/{storeTheme}', [StoreThemeController::class, 'update'])->name('themes.update');
        Route::post('themes/{storeTheme}/duplicate', [StoreThemeController::class, 'duplicate'])->name('themes.duplicate');
        Route::post('themes/{storeTheme}/publish', [StoreThemeController::class, 'publish'])->name('themes.publish');
        Route::delete('themes/{storeTheme}', [StoreThemeController::class, 'destroy'])->name('themes.destroy');
    });
