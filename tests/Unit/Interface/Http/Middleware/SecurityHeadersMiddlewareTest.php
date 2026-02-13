<?php

declare(strict_types=1);

use App\Interface\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('adds all 7 security headers to response', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return new Response('test');
    });

    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains');

    expect($response->headers->get('X-Content-Type-Options'))
        ->toBe('nosniff');

    expect($response->headers->get('X-Frame-Options'))
        ->toBe('DENY');

    expect($response->headers->get('X-XSS-Protection'))
        ->toBe('1; mode=block');

    expect($response->headers->get('Referrer-Policy'))
        ->toBe('strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))
        ->toBe("default-src 'none'; frame-ancestors 'none'");

    expect($response->headers->get('Permissions-Policy'))
        ->toBe('geolocation=(), microphone=(), camera=()');
});

test('does not remove existing response headers', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        $testResponse = new Response('test');
        $testResponse->headers->set('X-Custom-Header', 'custom-value');
        $testResponse->headers->set('Content-Type', 'application/json');
        $testResponse->headers->set('Cache-Control', 'no-cache');
        return $testResponse;
    });

    expect($response->headers->get('X-Custom-Header'))
        ->toBe('custom-value');

    expect($response->headers->get('Content-Type'))
        ->toBe('application/json');

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-cache');

    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains');

    expect($response->headers->get('X-Content-Type-Options'))
        ->toBe('nosniff');
});
