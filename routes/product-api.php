<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MediaAiController;
use App\Http\Controllers\Api\V1\MediaAttachmentController;
use App\Http\Controllers\Api\V1\MediaController;
use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\ProductController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductImageController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductOptionController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductOptionValueController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductVariantController;

Route::middleware(['api', 'auth:sanctum', 'user.scope:store', 'store', 'store.member', 'store.bindings'])
    ->prefix('api/v1/store')
    ->name('api.v1.store.')
    ->group(function (): void {
        Route::post('media/ai/generate', [MediaAiController::class, 'generate'])
            ->middleware('throttle:6,1')
            ->name('media.ai.generate');
        Route::post('media/uploads', [MediaController::class, 'store'])->name('media.uploads.store');
        Route::post('media/{media}/ai', [MediaAiController::class, 'run'])
            ->middleware('throttle:10,1')
            ->name('media.ai.run');
        Route::get('media/{media}/ai-results', [MediaAiController::class, 'history'])
            ->name('media.ai.history');
        Route::get('media/{media}/content', [MediaController::class, 'content'])->name('media.content');
        Route::post('media/{media}/complete', [MediaController::class, 'complete'])->name('media.complete');
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::post('products/{product}/media', [MediaAttachmentController::class, 'attachProduct'])
            ->name('products.media.attach');
        Route::delete('products/{product}/media/{media}', [MediaAttachmentController::class, 'detachProduct'])
            ->name('products.media.detach');
        Route::put('products/{product}/media/{media}/primary', [MediaAttachmentController::class, 'setPrimary'])
            ->name('products.media.primary');
        Route::post('product-variants/{variant}/media', [MediaAttachmentController::class, 'attachVariant'])
            ->name('product-variants.media.attach');
        Route::delete('product-variants/{variant}/media/{media}', [MediaAttachmentController::class, 'detachVariant'])
            ->name('product-variants.media.detach');
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
        Route::get('products/{product}/options', [ProductOptionController::class, 'index'])
            ->name('products.options.index');
        Route::post('products/{product}/options', [ProductOptionController::class, 'store'])
            ->name('products.options.store');
        Route::get('products/{product}/options/{option}', [ProductOptionController::class, 'show'])
            ->name('products.options.show');
        Route::patch('products/{product}/options/{option}', [ProductOptionController::class, 'update'])
            ->name('products.options.update');
        Route::delete('products/{product}/options/{option}', [ProductOptionController::class, 'destroy'])
            ->name('products.options.destroy');
        Route::get('products/{product}/options/{option}/values', [ProductOptionValueController::class, 'index'])
            ->name('products.options.values.index');
        Route::post('products/{product}/options/{option}/values', [ProductOptionValueController::class, 'store'])
            ->name('products.options.values.store');
        Route::get('products/{product}/options/{option}/values/{value}', [ProductOptionValueController::class, 'show'])
            ->name('products.options.values.show');
        Route::patch('products/{product}/options/{option}/values/{value}', [ProductOptionValueController::class, 'update'])
            ->name('products.options.values.update');
        Route::delete('products/{product}/options/{option}/values/{value}', [ProductOptionValueController::class, 'destroy'])
            ->name('products.options.values.destroy');
        Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])
            ->name('products.variants.index');
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])
            ->name('products.variants.store');
        Route::get('products/{product}/variants/{variant}', [ProductVariantController::class, 'show'])
            ->name('products.variants.show');
        Route::patch('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
            ->name('products.variants.update');
        Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])
            ->name('products.variants.destroy');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
