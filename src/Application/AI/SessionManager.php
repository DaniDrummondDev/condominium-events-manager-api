<?php

declare(strict_types=1);

namespace Application\AI;

use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\Redis;

class SessionManager
{
    private const KEY_PREFIX = 'ai_session:';

    public function getOrCreateSession(?string $sessionId, string $userId): string
    {
        if ($sessionId !== null && $this->sessionExists($sessionId)) {
            $this->refreshTtl($sessionId);

            return $sessionId;
        }

        $newSessionId = Uuid::generate()->value();

        Redis::hset(
            self::KEY_PREFIX . $newSessionId . ':meta',
            'user_id', $userId,
            'created_at', now()->toIso8601String(),
        );

        $this->refreshTtl($newSessionId);

        return $newSessionId;
    }

    /**
     * @return array<array{role: string, content: string}>
     */
    public function getMessages(string $sessionId): array
    {
        $raw = Redis::lrange(self::KEY_PREFIX . $sessionId . ':messages', 0, -1);

        return array_map(
            fn (string $item): array => json_decode($item, true, 512, JSON_THROW_ON_ERROR),
            $raw,
        );
    }

    public function addMessage(string $sessionId, string $role, string $content): void
    {
        Redis::rpush(
            self::KEY_PREFIX . $sessionId . ':messages',
            json_encode(['role' => $role, 'content' => $content], JSON_THROW_ON_ERROR),
        );

        $this->refreshTtl($sessionId);
    }

    public function destroySession(string $sessionId): void
    {
        Redis::del(
            self::KEY_PREFIX . $sessionId . ':messages',
            self::KEY_PREFIX . $sessionId . ':meta',
        );
    }

    private function sessionExists(string $sessionId): bool
    {
        return (bool) Redis::exists(self::KEY_PREFIX . $sessionId . ':meta');
    }

    private function refreshTtl(string $sessionId): void
    {
        $ttlSeconds = (int) config('ai.session_ttl_minutes', 10) * 60;

        Redis::expire(self::KEY_PREFIX . $sessionId . ':messages', $ttlSeconds);
        Redis::expire(self::KEY_PREFIX . $sessionId . ':meta', $ttlSeconds);
    }
}
