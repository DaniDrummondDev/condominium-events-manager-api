<?php

declare(strict_types=1);

namespace Application\Tenant\Contracts;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

interface PendingRegistrationRepositoryInterface
{
    public function save(
        Uuid $id,
        string $slug,
        string $name,
        string $type,
        string $adminName,
        string $adminEmail,
        string $adminPasswordHash,
        ?string $adminPhone,
        string $planSlug,
        string $verificationTokenHash,
        DateTimeImmutable $expiresAt,
    ): void;

    /**
     * @return array<string, mixed>|null
     */
    public function findByTokenHash(string $tokenHash): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveBySlug(string $slug): ?array;

    public function markVerified(Uuid $id): void;

    public function deleteExpired(): int;
}
