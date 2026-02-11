<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Illuminate\Support\Facades\Route;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();

    // Register a test route that uses the tenant middlewares
    Route::middleware(['tenant.resolve', 'tenant.active'])
        ->get('/test/tenant-endpoint', fn () => response()->json(['ok' => true]));
});

// --- ResolveTenantMiddleware ---

test('returns 403 when X-Tenant-ID header is missing', function () {
    $response = $this->getJson('/test/tenant-endpoint');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'tenant_required',
        ]);
});

test('returns 403 when tenant ID does not exist', function () {
    $response = $this->getJson('/test/tenant-endpoint', [
        'X-Tenant-ID' => '00000000-0000-0000-0000-000000000000',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'tenant_not_found',
        ]);
});

test('resolves active tenant and binds TenantContext', function () {
    $tenant = TenantModel::query()->create([
        'id' => '01934b6e-3a45-7f8c-8d2e-1a2b3c4d5e6f',
        'slug' => 'condo-test',
        'name' => 'Condomínio Test',
        'type' => 'vertical',
        'status' => 'active',
        'database_name' => 'tenant_condo_test',
    ]);

    $response = $this->getJson('/test/tenant-endpoint', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(200)
        ->assertJson(['ok' => true]);
});

// --- EnsureTenantActive ---

test('returns 403 for suspended tenant', function () {
    $tenant = TenantModel::query()->create([
        'id' => '01934b6e-3a45-7f8c-8d2e-2a2b3c4d5e6f',
        'slug' => 'condo-suspended',
        'name' => 'Suspended Condo',
        'type' => 'vertical',
        'status' => 'suspended',
        'database_name' => 'tenant_condo_suspended',
    ]);

    $response = $this->getJson('/test/tenant-endpoint', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'tenant_suspended',
        ]);
});

test('returns 403 for canceled tenant', function () {
    $tenant = TenantModel::query()->create([
        'id' => '01934b6e-3a45-7f8c-8d2e-3a2b3c4d5e6f',
        'slug' => 'condo-canceled',
        'name' => 'Canceled Condo',
        'type' => 'horizontal',
        'status' => 'canceled',
        'database_name' => 'tenant_condo_canceled',
    ]);

    $response = $this->getJson('/test/tenant-endpoint', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'tenant_canceled',
        ]);
});

test('returns 403 for provisioning tenant (inactive)', function () {
    $tenant = TenantModel::query()->create([
        'id' => '01934b6e-3a45-7f8c-8d2e-4a2b3c4d5e6f',
        'slug' => 'condo-provisioning',
        'name' => 'Provisioning Condo',
        'type' => 'mixed',
        'status' => 'provisioning',
        'database_name' => 'tenant_condo_provisioning',
    ]);

    $response = $this->getJson('/test/tenant-endpoint', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'tenant_inactive',
        ]);
});

test('allows trial tenant through', function () {
    $tenant = TenantModel::query()->create([
        'id' => '01934b6e-3a45-7f8c-8d2e-5a2b3c4d5e6f',
        'slug' => 'condo-trial',
        'name' => 'Trial Condo',
        'type' => 'vertical',
        'status' => 'trial',
        'database_name' => 'tenant_condo_trial',
    ]);

    $response = $this->getJson('/test/tenant-endpoint', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(200)
        ->assertJson(['ok' => true]);
});

test('allows past_due tenant through', function () {
    $tenant = TenantModel::query()->create([
        'id' => '01934b6e-3a45-7f8c-8d2e-6a2b3c4d5e6f',
        'slug' => 'condo-pastdue',
        'name' => 'PastDue Condo',
        'type' => 'vertical',
        'status' => 'past_due',
        'database_name' => 'tenant_condo_pastdue',
    ]);

    $response = $this->getJson('/test/tenant-endpoint', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(200)
        ->assertJson(['ok' => true]);
});
