<?php

declare(strict_types=1);

/**
 * Tenant API Routes
 *
 * Rotas do tenant (espacos, reservas, governanca, comunicacao).
 * Prefix: /tenant
 * Auth: Tenant JWT + ResolveTenantMiddleware + EnsureTenantActive
 */

use App\Interface\Http\Controllers\Auth\TenantAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]))->name('tenant.health');

// Auth routes (public — tenant resolved by slug in request body)
Route::prefix('auth')->group(function () {
    Route::post('/login', [TenantAuthController::class, 'login'])
        ->middleware('throttle:auth-login')
        ->name('tenant.auth.login');

    Route::post('/refresh', [TenantAuthController::class, 'refresh'])
        ->middleware(['throttle:auth-refresh', 'auth.jwt', 'tenant.resolve', 'tenant.active'])
        ->name('tenant.auth.refresh');
});

// Auth routes (authenticated — tenant resolved from JWT)
Route::prefix('auth')->middleware(['auth.jwt', 'tenant.resolve', 'tenant.active'])->group(function () {
    Route::post('/logout', [TenantAuthController::class, 'logout'])
        ->name('tenant.auth.logout');
});
