<?php

declare(strict_types=1);

/**
 * Tenant API Routes
 *
 * Rotas do tenant (espacos, reservas, governanca, comunicacao).
 * Prefix: /tenant
 * Auth: Tenant JWT + ResolveTenantMiddleware + EnsureTenantActive
 */

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]))->name('tenant.health');
