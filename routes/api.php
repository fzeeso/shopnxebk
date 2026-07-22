<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('health')->group(function (): void {
    Route::get('live', [HealthController::class, 'live'])->name('health.live');
    Route::get('ready', [HealthController::class, 'ready'])->name('health.ready');
});
