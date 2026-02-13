<?php

declare(strict_types=1);

use App\Interface\Http\Middleware\RequestLoggingMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

test('sanitizes sensitive fields from payload', function () {
    Log::spy();

    $middleware = new RequestLoggingMiddleware();
    $request = Request::create('/test', 'POST', [
        'username' => 'john_doe',
        'password' => 'secret123',
        'token' => 'abc-token-xyz',
        'email' => 'john@example.com',
    ]);

    $middleware->handle($request, function ($req) {
        return new Response('test', 200);
    });

    Log::shouldHaveReceived('log')
        ->once()
        ->with('info', 'HTTP Request', \Mockery::on(function ($context) {
            return $context['method'] === 'POST'
                && $context['uri'] === '/test'
                && $context['status'] === 200
                && isset($context['payload']['username'])
                && $context['payload']['username'] === 'john_doe'
                && isset($context['payload']['email'])
                && $context['payload']['email'] === 'john@example.com'
                && !isset($context['payload']['password'])
                && !isset($context['payload']['token']);
        }));
});

test('logs response with duration_ms >= 0', function () {
    Log::spy();

    $middleware = new RequestLoggingMiddleware();
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function ($req) {
        usleep(1000); // 1ms delay
        return new Response('test', 200);
    });

    Log::shouldHaveReceived('log')
        ->once()
        ->with('info', 'HTTP Request', \Mockery::on(function ($context) {
            return isset($context['duration_ms'])
                && is_numeric($context['duration_ms'])
                && $context['duration_ms'] >= 0
                && $context['method'] === 'GET'
                && $context['uri'] === '/test'
                && $context['status'] === 200
                && isset($context['ip'])
                && isset($context['user_agent']);
        }));
});

test('uses error level for 500 status responses', function () {
    Log::spy();

    $middleware = new RequestLoggingMiddleware();
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function ($req) {
        return new Response('Internal Server Error', 500);
    });

    Log::shouldHaveReceived('log')
        ->once()
        ->with('error', 'HTTP Request', \Mockery::on(function ($context) {
            return $context['method'] === 'GET'
                && $context['uri'] === '/test'
                && $context['status'] === 500
                && isset($context['duration_ms'])
                && $context['duration_ms'] >= 0;
        }));
});

test('truncates large payload', function () {
    Log::spy();

    $middleware = new RequestLoggingMiddleware();
    $largeData = str_repeat('x', 2000);
    $request = Request::create('/test', 'POST', [
        'large_field' => $largeData,
    ]);

    $middleware->handle($request, function ($req) {
        return new Response('test', 200);
    });

    Log::shouldHaveReceived('log')
        ->once()
        ->with('info', 'HTTP Request', \Mockery::on(function ($context) {
            return isset($context['payload']['_truncated'])
                && $context['payload']['_truncated'] === true
                && isset($context['payload']['_size'])
                && $context['payload']['_size'] > 1000;
        }));
});
