<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Models\PlatformUserModel;
use Domain\Shared\ValueObjects\Uuid;
use Tests\Traits\GeneratesJwtTokens;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, GeneratesJwtTokens::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();
    $this->setUpJwtTestKeys();
});

test('logout with valid token returns success', function () {
    $userId = Uuid::fromString('01934b6e-3a45-7f8c-8d2e-aaaaaaaaaaaa');

    PlatformUserModel::query()->create([
        'id' => $userId->value(),
        'name' => 'Admin',
        'email' => 'admin@platform.test',
        'password_hash' => password_hash('Password1', PASSWORD_BCRYPT),
        'role' => 'platform_admin',
        'status' => 'active',
        'mfa_enabled' => false,
        'failed_login_attempts' => 0,
    ]);

    $token = $this->generateAccessToken(userId: $userId);

    $response = $this->postJson('/platform/auth/logout', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Successfully logged out');
});

test('logout without token returns 401', function () {
    $response = $this->postJson('/platform/auth/logout');

    $response->assertStatus(401)
        ->assertJsonPath('error', 'unauthenticated');
});
