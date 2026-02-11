<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\PlatformUserModel;
use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use DateTimeImmutable;
use Domain\Auth\Entities\PlatformUser;
use Domain\Auth\Enums\PlatformRole;
use Domain\Auth\Enums\UserStatus;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPlatformUserRepository implements PlatformUserRepositoryInterface
{
    public function findByEmail(string $email): ?PlatformUser
    {
        $model = PlatformUserModel::query()->where('email', $email)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findById(Uuid $id): ?PlatformUser
    {
        $model = PlatformUserModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function save(PlatformUser $user): void
    {
        PlatformUserModel::query()->updateOrCreate(
            ['id' => $user->id()->value()],
            [
                'email' => $user->email(),
                'name' => $user->name(),
                'password_hash' => $user->passwordHash(),
                'role' => $user->role()->value,
                'status' => $user->status()->value,
                'mfa_secret' => $user->mfaSecret(),
                'mfa_enabled' => $user->mfaEnabled(),
                'failed_login_attempts' => $user->failedLoginAttempts(),
                'locked_until' => $user->lockedUntil()?->format('Y-m-d H:i:s'),
                'last_login_at' => $user->lastLoginAt()?->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function toDomain(PlatformUserModel $model): PlatformUser
    {
        /** @var string|null $lockedUntilRaw */
        $lockedUntilRaw = $model->getRawOriginal('locked_until');

        /** @var string|null $lastLoginAtRaw */
        $lastLoginAtRaw = $model->getRawOriginal('last_login_at');

        return new PlatformUser(
            id: Uuid::fromString($model->id),
            email: $model->email,
            name: $model->name,
            passwordHash: $model->password_hash,
            role: PlatformRole::from($model->role),
            status: UserStatus::from($model->status),
            mfaSecret: $model->mfa_secret,
            mfaEnabled: (bool) $model->mfa_enabled,
            failedLoginAttempts: (int) $model->failed_login_attempts,
            lockedUntil: $lockedUntilRaw !== null ? new DateTimeImmutable($lockedUntilRaw) : null,
            lastLoginAt: $lastLoginAtRaw !== null ? new DateTimeImmutable($lastLoginAtRaw) : null,
        );
    }
}
