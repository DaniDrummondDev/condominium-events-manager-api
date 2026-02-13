<?php

declare(strict_types=1);

use Application\AI\SessionManager;
use Illuminate\Support\Facades\Redis;

afterEach(fn () => Mockery::close());

test('creates new session when no session id provided', function () {
    Redis::shouldReceive('exists')->never();
    Redis::shouldReceive('hset')->once();
    Redis::shouldReceive('expire')->twice();

    config(['ai.session_ttl_minutes' => 10]);

    $manager = new SessionManager();

    $sessionId = $manager->getOrCreateSession(null, 'user-1');

    expect($sessionId)->toBeString()
        ->and(strlen($sessionId))->toBe(36);
});

test('reuses existing session when valid', function () {
    Redis::shouldReceive('exists')
        ->once()
        ->andReturn(1);
    Redis::shouldReceive('expire')->twice();

    config(['ai.session_ttl_minutes' => 10]);

    $manager = new SessionManager();

    $sessionId = $manager->getOrCreateSession('existing-session-id', 'user-1');

    expect($sessionId)->toBe('existing-session-id');
});

test('adds and retrieves messages', function () {
    Redis::shouldReceive('rpush')->once();
    Redis::shouldReceive('expire')->twice();

    config(['ai.session_ttl_minutes' => 10]);

    $manager = new SessionManager();
    $manager->addMessage('session-1', 'user', 'Hello');

    Redis::shouldReceive('lrange')
        ->once()
        ->with('ai_session:session-1:messages', 0, -1)
        ->andReturn([json_encode(['role' => 'user', 'content' => 'Hello'])]);

    $messages = $manager->getMessages('session-1');

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['role'])->toBe('user')
        ->and($messages[0]['content'])->toBe('Hello');
});

test('destroys session', function () {
    Redis::shouldReceive('del')
        ->once()
        ->with('ai_session:session-1:messages', 'ai_session:session-1:meta');

    $manager = new SessionManager();
    $manager->destroySession('session-1');
});
