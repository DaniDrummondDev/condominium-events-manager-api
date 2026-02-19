<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Models\PendingRegistrationModel;
use App\Infrastructure\Persistence\Platform\Models\PlanModel;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\Queue;
use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();
});

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'condominium' => [
            'name' => 'Condomínio Solar',
            'slug' => 'condominio-solar',
            'type' => 'vertical',
        ],
        'admin' => [
            'name' => 'João Silva',
            'email' => 'joao@test.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ],
        'plan_slug' => 'basico',
    ], $overrides);
}

// --- Public Plans ---

test('GET /public/plans returns only active plans', function () {
    $this->createPlanInDatabase('Básico', 'basico', 9900);
    $this->createPlanInDatabase('Premium', 'premium', 29900);

    PlanModel::query()->create([
        'id' => Uuid::generate()->value(),
        'name' => 'Archived',
        'slug' => 'archived',
        'status' => 'archived',
    ]);

    $response = $this->getJson('/api/v1/platform/public/plans');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('GET /public/plans returns plan name, slug, prices and features', function () {
    $this->createPlanInDatabase('Básico', 'basico', 9900);

    $response = $this->getJson('/api/v1/platform/public/plans');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'name',
                    'slug',
                    'prices',
                    'features',
                ],
            ],
        ]);
});

test('GET /public/plans does not expose internal IDs', function () {
    $this->createPlanInDatabase('Básico', 'basico', 9900);

    $response = $this->getJson('/api/v1/platform/public/plans');

    $response->assertStatus(200);

    $plan = $response->json('data.0');
    expect($plan)->not->toHaveKey('id')
        ->and($plan)->not->toHaveKey('status')
        ->and($plan)->not->toHaveKey('current_version');
});

test('GET /public/plans returns empty array when no active plans', function () {
    $response = $this->getJson('/api/v1/platform/public/plans');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

// --- Public Register (creates PendingRegistration, NOT Tenant) ---

test('POST /public/register returns 202 with verification message', function () {
    Queue::fake();
    $this->createPlanInDatabase('Básico', 'basico');

    $response = $this->postJson('/api/v1/platform/public/register', validRegistrationPayload());

    $response->assertStatus(202)
        ->assertJsonPath('data.slug', 'condominio-solar')
        ->assertJsonPath('data.message', 'Verifique seu email para continuar o cadastro.');
});

test('POST /public/register creates pending registration (not tenant)', function () {
    Queue::fake();
    $this->createPlanInDatabase('Básico', 'basico');

    $this->postJson('/api/v1/platform/public/register', validRegistrationPayload());

    $pending = PendingRegistrationModel::query()->where('slug', 'condominio-solar')->first();
    expect($pending)->not->toBeNull()
        ->and($pending->admin_email)->toBe('joao@test.com')
        ->and($pending->admin_name)->toBe('João Silva')
        ->and($pending->plan_slug)->toBe('basico')
        ->and($pending->verified_at)->toBeNull();

    $tenant = TenantModel::query()->where('slug', 'condominio-solar')->first();
    expect($tenant)->toBeNull();
});

test('POST /public/register stores hashed password in pending registration', function () {
    Queue::fake();
    $this->createPlanInDatabase('Básico', 'basico');

    $this->postJson('/api/v1/platform/public/register', validRegistrationPayload());

    $pending = PendingRegistrationModel::query()->where('slug', 'condominio-solar')->first();
    expect($pending->admin_password_hash)->toStartWith('$2y$');
});

test('POST /public/register stores hashed verification token', function () {
    Queue::fake();
    $this->createPlanInDatabase('Básico', 'basico');

    $this->postJson('/api/v1/platform/public/register', validRegistrationPayload());

    $pending = PendingRegistrationModel::query()->where('slug', 'condominio-solar')->first();
    expect($pending->verification_token_hash)->toHaveLength(64);
});

test('POST /public/register validates required fields', function () {
    $response = $this->postJson('/api/v1/platform/public/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'condominium.name',
            'condominium.slug',
            'condominium.type',
            'admin.name',
            'admin.email',
            'admin.password',
            'plan_slug',
        ]);
});

test('POST /public/register rejects duplicate slug in tenants', function () {
    Queue::fake();
    $this->createPlanInDatabase('Básico', 'basico');

    TenantModel::query()->create([
        'id' => Uuid::generate()->value(),
        'slug' => 'existente',
        'name' => 'Existente',
        'type' => 'vertical',
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/v1/platform/public/register', validRegistrationPayload([
        'condominium' => [
            'name' => 'Novo',
            'slug' => 'existente',
            'type' => 'vertical',
        ],
    ]));

    $response->assertStatus(422);
});

test('POST /public/register rejects invalid condominium type', function () {
    $this->createPlanInDatabase('Básico', 'basico');

    $response = $this->postJson('/api/v1/platform/public/register', validRegistrationPayload([
        'condominium' => [
            'name' => 'Solar',
            'slug' => 'solar',
            'type' => 'townhouse',
        ],
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['condominium.type']);
});

test('POST /public/register rejects non-existent plan', function () {
    $response = $this->postJson('/api/v1/platform/public/register', validRegistrationPayload([
        'plan_slug' => 'inexistente',
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['plan_slug']);
});

test('POST /public/register rejects weak password', function () {
    $this->createPlanInDatabase('Básico', 'basico');

    $response = $this->postJson('/api/v1/platform/public/register', validRegistrationPayload([
        'admin' => [
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ],
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['admin.password']);
});

test('POST /public/register rejects mismatched password confirmation', function () {
    $this->createPlanInDatabase('Básico', 'basico');

    $response = $this->postJson('/api/v1/platform/public/register', validRegistrationPayload([
        'admin' => [
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'secret1234',
            'password_confirmation' => 'different',
        ],
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['admin.password']);
});

// --- Verify Registration ---

test('GET /public/register/verify with valid token creates tenant', function () {
    Queue::fake();
    $this->createPlanInDatabase('Básico', 'basico');

    $plainToken = bin2hex(random_bytes(32));

    PendingRegistrationModel::query()->create([
        'id' => Uuid::generate()->value(),
        'slug' => 'condo-verificar',
        'name' => 'Condomínio Verificar',
        'type' => 'vertical',
        'admin_name' => 'Maria',
        'admin_email' => 'maria@test.com',
        'admin_password_hash' => password_hash('secret1234', PASSWORD_BCRYPT),
        'admin_phone' => null,
        'plan_slug' => 'basico',
        'verification_token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->getJson("/api/v1/platform/public/register/verify?token={$plainToken}");

    $response->assertStatus(200)
        ->assertJsonPath('data.tenant_slug', 'condo-verificar')
        ->assertJsonPath('data.status', 'provisioning');

    $tenant = TenantModel::query()->where('slug', 'condo-verificar')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->status)->toBe('provisioning');
});

test('GET /public/register/verify marks pending as verified', function () {
    Queue::fake();
    $this->createPlanInDatabase('Básico', 'basico');

    $plainToken = bin2hex(random_bytes(32));
    $pendingId = Uuid::generate()->value();

    PendingRegistrationModel::query()->create([
        'id' => $pendingId,
        'slug' => 'condo-mark',
        'name' => 'Condo Mark',
        'type' => 'horizontal',
        'admin_name' => 'Carlos',
        'admin_email' => 'carlos@test.com',
        'admin_password_hash' => password_hash('secret1234', PASSWORD_BCRYPT),
        'admin_phone' => null,
        'plan_slug' => 'basico',
        'verification_token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->addHours(24),
    ]);

    $this->getJson("/api/v1/platform/public/register/verify?token={$plainToken}");

    $pending = PendingRegistrationModel::query()->find($pendingId);
    expect($pending->verified_at)->not->toBeNull();
});

test('GET /public/register/verify returns 404 for invalid token', function () {
    $response = $this->getJson('/api/v1/platform/public/register/verify?token=invalid-token');

    $response->assertStatus(404)
        ->assertJsonPath('error', 'VERIFICATION_TOKEN_INVALID');
});

test('GET /public/register/verify returns 410 for expired token', function () {
    $plainToken = bin2hex(random_bytes(32));

    PendingRegistrationModel::query()->create([
        'id' => Uuid::generate()->value(),
        'slug' => 'condo-expired',
        'name' => 'Expired Condo',
        'type' => 'vertical',
        'admin_name' => 'Admin',
        'admin_email' => 'admin@test.com',
        'admin_password_hash' => password_hash('secret', PASSWORD_BCRYPT),
        'admin_phone' => null,
        'plan_slug' => 'basico',
        'verification_token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->getJson("/api/v1/platform/public/register/verify?token={$plainToken}");

    $response->assertStatus(410)
        ->assertJsonPath('error', 'VERIFICATION_TOKEN_EXPIRED');
});

test('GET /public/register/verify returns 400 when token is missing', function () {
    $response = $this->getJson('/api/v1/platform/public/register/verify');

    $response->assertStatus(400)
        ->assertJsonPath('error', 'VERIFICATION_TOKEN_REQUIRED');
});

test('GET /public/register/verify returns 404 for already-used token', function () {
    $plainToken = bin2hex(random_bytes(32));

    PendingRegistrationModel::query()->create([
        'id' => Uuid::generate()->value(),
        'slug' => 'condo-used',
        'name' => 'Used Condo',
        'type' => 'vertical',
        'admin_name' => 'Admin',
        'admin_email' => 'admin@test.com',
        'admin_password_hash' => password_hash('secret', PASSWORD_BCRYPT),
        'admin_phone' => null,
        'plan_slug' => 'basico',
        'verification_token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->addHours(24),
        'verified_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/platform/public/register/verify?token={$plainToken}");

    $response->assertStatus(404)
        ->assertJsonPath('error', 'VERIFICATION_TOKEN_INVALID');
});
