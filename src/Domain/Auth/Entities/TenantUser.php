<?php

declare(strict_types=1);

namespace Domain\Auth\Entities;

use DateTimeImmutable;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Auth\Enums\TenantRole;
use Domain\Auth\Enums\TenantUserStatus;
use Domain\Shared\ValueObjects\Uuid;

class TenantUser
{
    private const int MAX_FAILED_ATTEMPTS = 10;

    private const int LOCKOUT_MINUTES = 30;

    public function __construct(
        private readonly Uuid $id,
        private readonly string $email,
        private string $name,
        private string $passwordHash,
        private readonly TenantRole $role,
        private TenantUserStatus $status,
        private ?string $phone = null,
        private ?string $mfaSecret = null,
        private bool $mfaEnabled = false,
        private int $failedLoginAttempts = 0,
        private ?DateTimeImmutable $lockedUntil = null,
        private ?DateTimeImmutable $lastLoginAt = null,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): TenantRole
    {
        return $this->role;
    }

    public function status(): TenantUserStatus
    {
        return $this->status;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function mfaSecret(): ?string
    {
        return $this->mfaSecret;
    }

    public function mfaEnabled(): bool
    {
        return $this->mfaEnabled;
    }

    public function failedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function lockedUntil(): ?DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function verifyPassword(string $plainText, PasswordHasherInterface $hasher): bool
    {
        return $hasher->verify($plainText, $this->passwordHash);
    }

    public function isLocked(DateTimeImmutable $now): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }

        return $now < $this->lockedUntil;
    }

    public function incrementFailedAttempts(DateTimeImmutable $now): void
    {
        $this->failedLoginAttempts++;

        if ($this->failedLoginAttempts >= self::MAX_FAILED_ATTEMPTS) {
            $this->lockedUntil = $now->modify('+'.self::LOCKOUT_MINUTES.' minutes');
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->failedLoginAttempts = 0;
        $this->lockedUntil = null;
    }

    public function recordLogin(DateTimeImmutable $now): void
    {
        $this->lastLoginAt = $now;
        $this->resetFailedAttempts();
    }

    public function requiresMfa(): bool
    {
        return $this->mfaEnabled || $this->role->requiresMfa();
    }

    public function hasMfaConfigured(): bool
    {
        return $this->mfaEnabled && $this->mfaSecret !== null;
    }

    public function enableMfa(string $secret): void
    {
        $this->mfaSecret = $secret;
        $this->mfaEnabled = true;
    }

    public function lockoutRemainingMinutes(DateTimeImmutable $now): int
    {
        if ($this->lockedUntil === null) {
            return 0;
        }

        $remaining = $this->lockedUntil->getTimestamp() - $now->getTimestamp();

        return (int) ceil(max(0, $remaining) / 60);
    }
}
