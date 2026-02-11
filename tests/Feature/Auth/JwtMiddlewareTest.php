<?php

declare(strict_types=1);

use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\Route;
use Tests\Traits\GeneratesJwtTokens;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, GeneratesJwtTokens::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();
    $this->setUpJwtTestKeys();

    Route::middleware('auth.jwt')
        ->get('/test/protected', fn () => response()->json(['ok' => true]));
});

test('returns 401 when no token is provided', function () {
    $response = $this->getJson('/test/protected');

    $response->assertStatus(401)
        ->assertJsonPath('error', 'unauthenticated')
        ->assertJsonPath('message', 'Authentication token is required');
});

test('returns 401 for invalid token', function () {
    $response = $this->getJson('/test/protected', [
        'Authorization' => 'Bearer invalid.jwt.token',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'unauthenticated');
});

test('returns 401 for expired token', function () {
    $token = $this->generateExpiredToken();

    $response = $this->getJson('/test/protected', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(401);
});

test('returns 401 for MFA token type', function () {
    $token = $this->generateMfaToken();

    $response = $this->getJson('/test/protected', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('message', 'Invalid token type');
});

test('allows request with valid access token', function () {
    $token = $this->generateAccessToken();

    $response = $this->getJson('/test/protected', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJson(['ok' => true]);
});

test('returns 401 when Authorization header is not Bearer', function () {
    $response = $this->getJson('/test/protected', [
        'Authorization' => 'Basic dXNlcjpwYXNz',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'unauthenticated');
});

test('returns 401 for revoked token', function () {
    $userId = Uuid::generate();
    $token = $this->generateAccessToken(userId: $userId);

    // Revoke via cache (matching RedisTokenRevocation implementation)
    // Extract JTI from token to revoke it
    $parser = new \Lcobucci\JWT\Token\Parser(new \Lcobucci\JWT\Encoding\JoseEncoder);
    $parsed = $parser->parse($token);

    if ($parsed instanceof \Lcobucci\JWT\UnencryptedToken) {
        $jti = $parsed->claims()->get('jti');
        \Illuminate\Support\Facades\Cache::put("revoked_token:{$jti}", true, 900);
    }

    $response = $this->getJson('/test/protected', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(401);
});
