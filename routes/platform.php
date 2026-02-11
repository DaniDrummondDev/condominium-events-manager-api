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
use App\Interface\Http\Controllers\Platform\FeatureController;
use App\Interface\Http\Controllers\Platform\InvoiceController;
use App\Interface\Http\Controllers\Platform\PaymentController;
use App\Interface\Http\Controllers\Platform\PlanController;
use App\Interface\Http\Controllers\Platform\PlanVersionController;
use App\Interface\Http\Controllers\Platform\SubscriptionController;
use App\Interface\Http\Controllers\Platform\TenantFeatureOverrideController;
use App\Interface\Http\Controllers\Webhook\BillingWebhookController;
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

// Billing routes (authenticated)
Route::middleware('auth.jwt')->group(function () {
    // Plans
    Route::get('/plans', [PlanController::class, 'index'])->name('platform.plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('platform.plans.store');
    Route::get('/plans/{id}', [PlanController::class, 'show'])->name('platform.plans.show');
    Route::post('/plans/{id}/versions', [PlanVersionController::class, 'store'])
        ->name('platform.plans.versions.store');

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])
        ->name('platform.subscriptions.index');
    Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show'])
        ->name('platform.subscriptions.show');
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel'])
        ->name('platform.subscriptions.cancel');

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('platform.invoices.index');
    Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('platform.invoices.show');

    // Payments
    Route::get('/payments/{id}', [PaymentController::class, 'show'])->name('platform.payments.show');
    Route::post('/payments/{id}/refund', [PaymentController::class, 'refund'])
        ->name('platform.payments.refund');

    // Features
    Route::get('/features', [FeatureController::class, 'index'])->name('platform.features.index');
    Route::post('/features', [FeatureController::class, 'store'])->name('platform.features.store');
    Route::get('/features/{id}', [FeatureController::class, 'show'])->name('platform.features.show');

    // Tenant Feature Overrides
    Route::get('/tenants/{tenantId}/features', [TenantFeatureOverrideController::class, 'index'])
        ->name('platform.tenants.features.index');
    Route::post('/tenants/{tenantId}/features', [TenantFeatureOverrideController::class, 'store'])
        ->name('platform.tenants.features.store');
    Route::delete('/tenants/{tenantId}/features/{overrideId}', [TenantFeatureOverrideController::class, 'destroy'])
        ->name('platform.tenants.features.destroy');
});

// Webhook (no JWT — validated via signature)
Route::post('/webhooks/billing', [BillingWebhookController::class, 'handle'])
    ->middleware('throttle:billing-webhook')
    ->name('platform.webhooks.billing');
