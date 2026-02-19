<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\PendingRegistrationModel;
use Application\Tenant\Contracts\PendingRegistrationRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPendingRegistrationRepository implements PendingRegistrationRepositoryInterface
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
    ): void {
        PendingRegistrationModel::query()->create([
            'id' => $id->value(),
            'slug' => $slug,
            'name' => $name,
            'type' => $type,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password_hash' => $adminPasswordHash,
            'admin_phone' => $adminPhone,
            'plan_slug' => $planSlug,
            'verification_token_hash' => $verificationTokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $model = PendingRegistrationModel::query()
            ->where('verification_token_hash', $tokenHash)
            ->whereNull('verified_at')
            ->first();

        return $model?->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveBySlug(string $slug): ?array
    {
        $model = PendingRegistrationModel::query()
            ->where('slug', $slug)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();

        return $model?->toArray();
    }

    public function markVerified(Uuid $id): void
    {
        PendingRegistrationModel::query()
            ->where('id', $id->value())
            ->update(['verified_at' => now()]);
    }

    public function deleteExpired(): int
    {
        return PendingRegistrationModel::query()
            ->where('expires_at', '<', now())
            ->whereNull('verified_at')
            ->delete();
    }
}
