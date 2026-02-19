<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Models\PlatformRefreshTokenModel;
use App\Infrastructure\Persistence\Platform\Models\PlatformUserModel;
use Domain\Shared\ValueObjects\Uuid;
use Tests\Traits\GeneratesJwtTokens;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, GeneratesJwtTokens::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();
    $this->setUpJwtTestKeys();
});

function createUserAndRefreshToken(): array
{
    $userId = '01934b6e-3a45-7f8c-8d2e-bbbbbbbbbbbb';
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    PlatformUserModel::query()->create([
        'id' => $userId,
        'name' => 'Admin',
        'email' => 'admin@platform.test',
        'password_hash' => password_hash('Password1', PASSWORD_BCRYPT),
        'role' => 'platform_admin',
        'status' => 'active',
        'mfa_enabled' => false,
        'failed_login_attempts' => 0,
    ]);

    PlatformRefreshTokenModel::query()->create([
        'id' => Uuid::generate()->value(),
        'user_id' => $userId,
        'token_hash' => $tokenHash,
        'parent_id' => null,
        'expires_at' => now()->addDays(7),
        'used_at' => null,
        'revoked_at' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestAgent',
        'created_at' => now(),
    ]);

    return [$userId, $rawToken];
}

test('refresh token returns new access and refresh tokens', function () {
    [$userId, $rawToken] = createUserAndRefreshToken();

    $response = $this->postJson('/api/v1/platform/auth/refresh', [
        'refresh_token' => $rawToken,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ],
        ]);
});

test('refresh with invalid token returns 401', function () {
    $response = $this->postJson('/api/v1/platform/auth/refresh', [
        'refresh_token' => 'invalid-token-value',
    ]);

    $response->assertStatus(401);
});

test('refresh validates required fields', function () {
    $response = $this->postJson('/api/v1/platform/auth/refresh', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['refresh_token']);
});

test('refresh token rotation marks old token as used', function () {
    [$userId, $rawToken] = createUserAndRefreshToken();

    $this->postJson('/api/v1/platform/auth/refresh', [
        'refresh_token' => $rawToken,
    ]);

    $oldToken = PlatformRefreshTokenModel::query()
        ->where('token_hash', hash('sha256', $rawToken))
        ->first();

    expect($oldToken->used_at)->not->toBeNull();
});

test('reusing an already-used refresh token returns 401', function () {
    [$userId, $rawToken] = createUserAndRefreshToken();

    // First use: should succeed
    $this->postJson('/api/v1/platform/auth/refresh', [
        'refresh_token' => $rawToken,
    ])->assertStatus(200);

    // Second use: reuse detected, should fail
    $response = $this->postJson('/api/v1/platform/auth/refresh', [
        'refresh_token' => $rawToken,
    ]);

    $response->assertStatus(401);
});
