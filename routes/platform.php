<?php

declare(strict_types=1);

/**
 * Platform API Routes
 *
 * Rotas da plataforma (admin, billing, tenant management).
 * Prefix: /platform
 * Auth: Platform JWT (platform_owner, platform_admin)
 */

use App\Interface\Http\Controllers\Auth\MfaController;
use App\Interface\Http\Controllers\Auth\PlatformAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]))->name('platform.health');

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [PlatformAuthController::class, 'login'])
        ->middleware('throttle:auth-login')
        ->name('platform.auth.login');

    Route::post('/refresh', [PlatformAuthController::class, 'refresh'])
        ->middleware('throttle:auth-refresh')
        ->name('platform.auth.refresh');

    Route::post('/mfa/verify', [MfaController::class, 'verify'])
        ->middleware('throttle:auth-login')
        ->name('platform.auth.mfa.verify');
});

// Auth routes (authenticated)
Route::prefix('auth')->middleware('auth.jwt')->group(function () {
    Route::post('/logout', [PlatformAuthController::class, 'logout'])
        ->name('platform.auth.logout');

    Route::post('/mfa/setup', [MfaController::class, 'setup'])
        ->name('platform.auth.mfa.setup');

    Route::post('/mfa/confirm', [MfaController::class, 'confirmSetup'])
        ->name('platform.auth.mfa.confirm');
});
