<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Authentication\Http\Controllers\Api\V1\AuthController;
use Modules\Authentication\Http\Controllers\Api\V1\MfaChallengeController;
use Modules\Authentication\Http\Controllers\Api\V1\MfaController;

Route::middleware('api')->prefix('api/v1/auth')->name('api.v1.auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth.login')->name('login');
    Route::post('token', [AuthController::class, 'token'])->middleware('throttle:auth.token')->name('token');
    Route::post('mfa/challenge', [MfaChallengeController::class, 'store'])->middleware('throttle:auth.mfa')->name('mfa.challenge');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('reset-password');
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::get('stores', [AuthController::class, 'stores'])->name('stores');
        Route::post('email/verification-notification', [AuthController::class, 'sendVerification'])->middleware('throttle:6,1')->name('verification.send');
        Route::get('mfa', [MfaController::class, 'status'])->name('mfa.status');
        Route::middleware('throttle:auth.mfa-management')->group(function (): void {
            Route::post('mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
            Route::post('mfa/confirm', [MfaController::class, 'confirm'])->name('mfa.confirm');
            Route::post('mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes'])->name('mfa.recovery-codes');
            Route::delete('mfa', [MfaController::class, 'disable'])->name('mfa.disable');
        });
        Route::get('tokens', [AuthController::class, 'tokens'])->name('tokens.index');
        Route::post('tokens', [AuthController::class, 'createToken'])->name('tokens.store');
        Route::delete('tokens/{token}', [AuthController::class, 'revokeToken'])->name('tokens.destroy');
    });
});
