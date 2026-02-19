<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Models\PlatformUserModel;
use Tests\Traits\GeneratesJwtTokens;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, GeneratesJwtTokens::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();
    $this->setUpJwtTestKeys();
});

function createPlatformUserInDb(array $overrides = []): PlatformUserModel
{
    return PlatformUserModel::query()->create(array_merge([
        'id' => '01934b6e-3a45-7f8c-8d2e-aaaaaaaaaaaa',
        'name' => 'Admin User',
        'email' => 'admin@platform.test',
        'password_hash' => password_hash('SecurePass123', PASSWORD_BCRYPT),
        'role' => 'platform_admin',
        'status' => 'active',
        'mfa_enabled' => false,
        'failed_login_attempts' => 0,
    ], $overrides));
}

test('successful login returns access and refresh tokens', function () {
    createPlatformUserInDb();

    $response = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@platform.test',
        'password' => 'SecurePass123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ],
        ])
        ->assertJsonPath('data.token_type', 'bearer')
        ->assertJsonPath('data.expires_in', 900);
});

test('login with invalid credentials returns 401', function () {
    createPlatformUserInDb();

    $response = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@platform.test',
        'password' => 'WrongPassword',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'INVALID_CREDENTIALS');
});

test('login with non-existent email returns 401', function () {
    $response = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'nonexistent@platform.test',
        'password' => 'SecurePass123',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'INVALID_CREDENTIALS');
});

test('login with inactive account returns 401', function () {
    createPlatformUserInDb(['status' => 'inactive']);

    $response = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@platform.test',
        'password' => 'SecurePass123',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'ACCOUNT_DISABLED');
});

test('login with locked account returns 401', function () {
    createPlatformUserInDb([
        'failed_login_attempts' => 10,
        'locked_until' => now()->addMinutes(30),
    ]);

    $response = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@platform.test',
        'password' => 'SecurePass123',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'ACCOUNT_LOCKED');
});

test('login validates required fields', function () {
    $response = $this->postJson('/api/v1/platform/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('login with MFA configured returns mfa_required', function () {
    createPlatformUserInDb([
        'mfa_enabled' => true,
        'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        'role' => 'platform_owner',
    ]);

    $response = $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@platform.test',
        'password' => 'SecurePass123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'mfa_required',
                'mfa_token',
                'expires_in',
                'methods',
            ],
        ])
        ->assertJsonPath('data.mfa_required', true);
});

test('failed login increments failed_login_attempts', function () {
    createPlatformUserInDb();

    $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@platform.test',
        'password' => 'WrongPassword',
    ]);

    $user = PlatformUserModel::query()->where('email', 'admin@platform.test')->first();
    expect($user->failed_login_attempts)->toBe(1);
});
