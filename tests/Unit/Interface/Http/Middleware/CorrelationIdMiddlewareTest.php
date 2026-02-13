<?php

declare(strict_types=1);

use App\Interface\Http\Middleware\CorrelationIdMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('generates a UUID correlation ID when none present in request', function () {
    $middleware = new CorrelationIdMiddleware();
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return new Response('test');
    });

    $correlationId = $request->header(CorrelationIdMiddleware::HEADER);

    expect($correlationId)
        ->not->toBeNull()
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');

    expect($response->headers->get(CorrelationIdMiddleware::HEADER))
        ->toBe($correlationId);

    expect(app('correlation_id'))
        ->toBe($correlationId);
});

test('preserves existing correlation ID from request header', function () {
    $middleware = new CorrelationIdMiddleware();
    $existingId = 'existing-correlation-id-12345';
    $request = Request::create('/test', 'GET');
    $request->headers->set(CorrelationIdMiddleware::HEADER, $existingId);

    $response = $middleware->handle($request, function ($req) {
        return new Response('test');
    });

    expect($request->header(CorrelationIdMiddleware::HEADER))
        ->toBe($existingId);

    expect($response->headers->get(CorrelationIdMiddleware::HEADER))
        ->toBe($existingId);

    expect(app('correlation_id'))
        ->toBe($existingId);
});

test('sets correlation ID on response header', function () {
    $middleware = new CorrelationIdMiddleware();
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        $testResponse = new Response('test');
        $testResponse->headers->set('X-Custom-Header', 'custom-value');
        return $testResponse;
    });

    $correlationId = $request->header(CorrelationIdMiddleware::HEADER);

    expect($response->headers->get(CorrelationIdMiddleware::HEADER))
        ->toBe($correlationId);

    expect($response->headers->get('X-Custom-Header'))
        ->toBe('custom-value');
});
