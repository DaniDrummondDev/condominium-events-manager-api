<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

use Application\Auth\DTOs\RefreshTokenRecord;
use Domain\Shared\ValueObjects\Uuid;

interface RefreshTokenRepositoryInterface
{
    public function store(RefreshTokenRecord $record): void;

    public function findByTokenHash(string $hash): ?RefreshTokenRecord;

    public function markAsUsed(Uuid $id, \DateTimeImmutable $usedAt): void;

    public function revokeAllForUser(Uuid $userId): void;

    public function revokeChain(Uuid $tokenId): void;
}
