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
use App\Interface\Http\Controllers\Tenant\BlockController;
use App\Interface\Http\Controllers\Tenant\CondominiumRuleController;
use App\Interface\Http\Controllers\Tenant\ContestationController;
use App\Interface\Http\Controllers\Tenant\GovernanceController;
use App\Interface\Http\Controllers\Tenant\PenaltyPolicyController;
use App\Interface\Http\Controllers\Tenant\ReservationController;
use App\Interface\Http\Controllers\Tenant\ResidentController;
use App\Interface\Http\Controllers\Tenant\SpaceController;
use App\Interface\Http\Controllers\Tenant\UnitController;
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

// Resident activation (public — no JWT required)
Route::post('/residents/activate', [ResidentController::class, 'activate'])
    ->name('tenant.residents.activate');

// Authenticated tenant routes
Route::middleware(['auth.jwt', 'tenant.resolve', 'tenant.active'])->group(function () {
    // Blocks
    Route::get('/blocks', [BlockController::class, 'index'])->name('tenant.blocks.index');
    Route::post('/blocks', [BlockController::class, 'store'])->name('tenant.blocks.store');
    Route::get('/blocks/{id}', [BlockController::class, 'show'])->name('tenant.blocks.show');
    Route::put('/blocks/{id}', [BlockController::class, 'update'])->name('tenant.blocks.update');
    Route::delete('/blocks/{id}', [BlockController::class, 'destroy'])->name('tenant.blocks.destroy');

    // Units
    Route::get('/units', [UnitController::class, 'index'])->name('tenant.units.index');
    Route::post('/units', [UnitController::class, 'store'])->name('tenant.units.store');
    Route::get('/units/{id}', [UnitController::class, 'show'])->name('tenant.units.show');
    Route::put('/units/{id}', [UnitController::class, 'update'])->name('tenant.units.update');
    Route::post('/units/{id}/deactivate', [UnitController::class, 'deactivate'])->name('tenant.units.deactivate');

    // Residents
    Route::get('/units/{unitId}/residents', [ResidentController::class, 'index'])->name('tenant.residents.index');
    Route::post('/residents/invite', [ResidentController::class, 'invite'])->name('tenant.residents.invite');
    Route::get('/residents/{id}', [ResidentController::class, 'show'])->name('tenant.residents.show');
    Route::post('/residents/{id}/deactivate', [ResidentController::class, 'deactivate'])->name('tenant.residents.deactivate');

    // Spaces
    Route::get('/spaces', [SpaceController::class, 'index'])->name('tenant.spaces.index');
    Route::post('/spaces', [SpaceController::class, 'store'])->name('tenant.spaces.store');
    Route::get('/spaces/{id}', [SpaceController::class, 'show'])->name('tenant.spaces.show');
    Route::put('/spaces/{id}', [SpaceController::class, 'update'])->name('tenant.spaces.update');
    Route::patch('/spaces/{id}/status', [SpaceController::class, 'changeStatus'])->name('tenant.spaces.change-status');

    // Space Availability
    Route::get('/spaces/{id}/availability', [SpaceController::class, 'availabilityIndex'])->name('tenant.spaces.availability.index');
    Route::post('/spaces/{id}/availability', [SpaceController::class, 'availabilityStore'])->name('tenant.spaces.availability.store');
    Route::delete('/spaces/{id}/availability/{availabilityId}', [SpaceController::class, 'availabilityDestroy'])->name('tenant.spaces.availability.destroy');

    // Space Blocks
    Route::get('/spaces/{id}/blocks', [SpaceController::class, 'blockIndex'])->name('tenant.spaces.blocks.index');
    Route::post('/spaces/{id}/blocks', [SpaceController::class, 'blockStore'])->name('tenant.spaces.blocks.store');
    Route::delete('/spaces/{id}/blocks/{blockId}', [SpaceController::class, 'blockDestroy'])->name('tenant.spaces.blocks.destroy');

    // Space Rules
    Route::get('/spaces/{id}/rules', [SpaceController::class, 'ruleIndex'])->name('tenant.spaces.rules.index');
    Route::post('/spaces/{id}/rules', [SpaceController::class, 'ruleStore'])->name('tenant.spaces.rules.store');
    Route::put('/spaces/{id}/rules/{ruleId}', [SpaceController::class, 'ruleUpdate'])->name('tenant.spaces.rules.update');
    Route::delete('/spaces/{id}/rules/{ruleId}', [SpaceController::class, 'ruleDestroy'])->name('tenant.spaces.rules.destroy');

    // Available Slots (under spaces)
    Route::get('/spaces/{id}/available-slots', [ReservationController::class, 'availableSlots'])->name('tenant.spaces.available-slots');

    // Reservations
    Route::get('/reservations', [ReservationController::class, 'index'])->name('tenant.reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('tenant.reservations.store');
    Route::get('/reservations/{id}', [ReservationController::class, 'show'])->name('tenant.reservations.show');
    Route::post('/reservations/{id}/approve', [ReservationController::class, 'approve'])->name('tenant.reservations.approve');
    Route::post('/reservations/{id}/reject', [ReservationController::class, 'reject'])->name('tenant.reservations.reject');
    Route::post('/reservations/{id}/cancel', [ReservationController::class, 'cancel'])->name('tenant.reservations.cancel');
    Route::post('/reservations/{id}/check-in', [ReservationController::class, 'checkIn'])->name('tenant.reservations.check-in');
    Route::post('/reservations/{id}/complete', [ReservationController::class, 'complete'])->name('tenant.reservations.complete');
    Route::post('/reservations/{id}/no-show', [ReservationController::class, 'noShow'])->name('tenant.reservations.no-show');

    // Condominium Rules
    Route::get('/rules', [CondominiumRuleController::class, 'index'])->name('tenant.rules.index');
    Route::post('/rules', [CondominiumRuleController::class, 'store'])->name('tenant.rules.store');
    Route::get('/rules/{id}', [CondominiumRuleController::class, 'show'])->name('tenant.rules.show');
    Route::put('/rules/{id}', [CondominiumRuleController::class, 'update'])->name('tenant.rules.update');
    Route::delete('/rules/{id}', [CondominiumRuleController::class, 'destroy'])->name('tenant.rules.destroy');

    // Violations
    Route::get('/violations', [GovernanceController::class, 'listViolations'])->name('tenant.violations.index');
    Route::post('/violations', [GovernanceController::class, 'registerViolation'])->name('tenant.violations.store');
    Route::get('/violations/{id}', [GovernanceController::class, 'showViolation'])->name('tenant.violations.show');
    Route::post('/violations/{id}/uphold', [GovernanceController::class, 'upholdViolation'])->name('tenant.violations.uphold');
    Route::post('/violations/{id}/revoke', [GovernanceController::class, 'revokeViolation'])->name('tenant.violations.revoke');
    Route::post('/violations/{id}/contest', [ContestationController::class, 'submitContestation'])->name('tenant.violations.contest');

    // Contestations
    Route::get('/contestations', [ContestationController::class, 'listContestations'])->name('tenant.contestations.index');
    Route::get('/contestations/{id}', [ContestationController::class, 'showContestation'])->name('tenant.contestations.show');
    Route::post('/contestations/{id}/review', [ContestationController::class, 'reviewContestation'])->name('tenant.contestations.review');

    // Penalties
    Route::get('/penalties', [GovernanceController::class, 'listPenalties'])->name('tenant.penalties.index');
    Route::get('/penalties/{id}', [GovernanceController::class, 'showPenalty'])->name('tenant.penalties.show');
    Route::post('/penalties/{id}/revoke', [GovernanceController::class, 'revokePenalty'])->name('tenant.penalties.revoke');

    // Penalty Policies
    Route::get('/penalty-policies', [PenaltyPolicyController::class, 'index'])->name('tenant.penalty-policies.index');
    Route::post('/penalty-policies', [PenaltyPolicyController::class, 'store'])->name('tenant.penalty-policies.store');
    Route::get('/penalty-policies/{id}', [PenaltyPolicyController::class, 'show'])->name('tenant.penalty-policies.show');
    Route::put('/penalty-policies/{id}', [PenaltyPolicyController::class, 'update'])->name('tenant.penalty-policies.update');
    Route::delete('/penalty-policies/{id}', [PenaltyPolicyController::class, 'destroy'])->name('tenant.penalty-policies.destroy');
});
