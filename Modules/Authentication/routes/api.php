<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Authentication\Http\Controllers\Api\V1\AuthController;
use Modules\Authentication\Http\Controllers\Api\V1\MfaChallengeController;
use Modules\Authentication\Http\Controllers\Api\V1\MfaController;
use Modules\Authentication\Http\Controllers\Api\V1\PlatformUserController;

Route::middleware('api')->prefix('api/v1/auth')->name('api.v1.auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth.register')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth.login')->name('login');
    Route::post('token', [AuthController::class, 'token'])->middleware('throttle:auth.token')->name('token');
    Route::post('mfa/challenge', [MfaChallengeController::class, 'store'])->middleware('throttle:auth.mfa')->name('mfa.challenge');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('reset-password');
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::get('session', [AuthController::class, 'session'])->name('session');
        Route::get('interfaces', [AuthController::class, 'interfaces'])->name('interfaces');
        Route::get('stores', [AuthController::class, 'stores'])->middleware('user.scope:store')->name('stores');
        Route::post('email/verification-notification', [AuthController::class, 'sendVerification'])->middleware('throttle:6,1')->name('verification.send');
        Route::get('mfa', [MfaController::class, 'status'])->name('mfa.status');
        Route::middleware('throttle:auth.mfa-management')->group(function (): void {
            Route::post('mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
            Route::post('mfa/confirm', [MfaController::class, 'confirm'])->name('mfa.confirm');
            Route::post('mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes'])->name('mfa.recovery-codes');
            Route::delete('mfa', [MfaController::class, 'disable'])->name('mfa.disable');
        });
        Route::get('tokens', [AuthController::class, 'tokens'])->name('tokens.index');
        Route::post('tokens', [AuthController::class, 'createToken'])->middleware('throttle:auth.token-management')->name('tokens.store');
        Route::delete('tokens/{token}', [AuthController::class, 'revokeToken'])->middleware('throttle:auth.token-management')->name('tokens.destroy');
    });
});

Route::middleware(['api', 'auth:sanctum', 'user.scope:platform'])
    ->prefix('api/v1/platform')
    ->name('api.v1.platform.')
    ->group(function (): void {
        Route::get('roles', [PlatformUserController::class, 'roles'])->name('roles.index');
        Route::get('users', [PlatformUserController::class, 'index'])->name('users.index');
        Route::post('users', [PlatformUserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [PlatformUserController::class, 'show'])->name('users.show');
        Route::patch('users/{user}', [PlatformUserController::class, 'update'])->name('users.update');
    });
