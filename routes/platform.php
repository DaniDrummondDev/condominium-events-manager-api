<?php

declare(strict_types=1);

/**
 * Platform API Routes
 *
 * Rotas da plataforma (admin, billing, tenant management).
 * Prefix: /platform
 * Auth: Platform JWT (platform_owner, platform_admin)
 */

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]))->name('platform.health');
