<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\TenantUserModel;
use Application\Auth\Contracts\TenantUserRepositoryInterface;
use DateTimeImmutable;
use Domain\Auth\Entities\TenantUser;
use Domain\Auth\Enums\TenantRole;
use Domain\Auth\Enums\TenantUserStatus;
use Domain\Shared\ValueObjects\Uuid;

class EloquentTenantUserRepository implements TenantUserRepositoryInterface
{
    public function findByEmail(string $email): ?TenantUser
    {
        $model = TenantUserModel::query()->where('email', $email)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findById(Uuid $id): ?TenantUser
    {
        $model = TenantUserModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findByInvitationToken(string $token): ?TenantUser
    {
        $model = TenantUserModel::query()->where('invitation_token', $token)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(TenantUser $user): void
    {
        TenantUserModel::query()->updateOrCreate(
            ['id' => $user->id()->value()],
            [
                'email' => $user->email(),
                'name' => $user->name(),
                'password_hash' => $user->passwordHash(),
                'role' => $user->role()->value,
                'status' => $user->status()->value,
                'phone' => $user->phone(),
                'mfa_secret' => $user->mfaSecret(),
                'mfa_enabled' => $user->mfaEnabled(),
                'failed_login_attempts' => $user->failedLoginAttempts(),
                'locked_until' => $user->lockedUntil()?->format('Y-m-d H:i:s'),
                'last_login_at' => $user->lastLoginAt()?->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function saveInvitationToken(Uuid $userId, string $token, DateTimeImmutable $expiresAt): void
    {
        TenantUserModel::query()
            ->where('id', $userId->value())
            ->update([
                'invitation_token' => $token,
                'invitation_expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);
    }

    public function getInvitationExpiresAt(Uuid $userId): ?DateTimeImmutable
    {
        $model = TenantUserModel::query()->find($userId->value());

        if ($model === null) {
            return null;
        }

        /** @var string|null $expiresAtRaw */
        $expiresAtRaw = $model->getRawOriginal('invitation_expires_at');

        return $expiresAtRaw !== null ? new DateTimeImmutable($expiresAtRaw) : null;
    }

    public function clearInvitationToken(Uuid $userId): void
    {
        TenantUserModel::query()
            ->where('id', $userId->value())
            ->update([
                'invitation_token' => null,
                'invitation_expires_at' => null,
            ]);
    }

    private function toDomain(TenantUserModel $model): TenantUser
    {
        /** @var string|null $lockedUntilRaw */
        $lockedUntilRaw = $model->getRawOriginal('locked_until');

        /** @var string|null $lastLoginAtRaw */
        $lastLoginAtRaw = $model->getRawOriginal('last_login_at');

        return new TenantUser(
            id: Uuid::fromString($model->id),
            email: $model->email,
            name: $model->name,
            passwordHash: $model->password_hash,
            role: TenantRole::from($model->role),
            status: TenantUserStatus::from($model->status),
            phone: $model->phone,
            mfaSecret: $model->mfa_secret,
            mfaEnabled: (bool) $model->mfa_enabled,
            failedLoginAttempts: (int) $model->failed_login_attempts,
            lockedUntil: $lockedUntilRaw !== null ? new DateTimeImmutable($lockedUntilRaw) : null,
            lastLoginAt: $lastLoginAtRaw !== null ? new DateTimeImmutable($lastLoginAtRaw) : null,
        );
    }
}
