<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | RSA Key Paths
    |--------------------------------------------------------------------------
    |
    | RS256 requires an RSA key pair. The private key signs tokens,
    | the public key verifies them. In production, use 4096-bit keys.
    |
    */

    'private_key_path' => env('JWT_PRIVATE_KEY_PATH', storage_path('keys/jwt-private.pem')),
    'public_key_path' => env('JWT_PUBLIC_KEY_PATH', storage_path('keys/jwt-public.pem')),

    /*
    |--------------------------------------------------------------------------
    | Token Settings
    |--------------------------------------------------------------------------
    */

    'issuer' => env('JWT_ISSUER', 'condominium-events-api'),
    'audience' => env('JWT_AUDIENCE', 'condominium-events-client'),

    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),      // 15 minutes
    'mfa_ttl' => (int) env('JWT_MFA_TTL', 300),            // 5 minutes

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 604800),  // 7 days

];
