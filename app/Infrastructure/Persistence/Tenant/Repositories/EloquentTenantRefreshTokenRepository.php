<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\TenantRefreshTokenModel;
use Application\Auth\Contracts\TenantRefreshTokenRepositoryInterface;
use Application\Auth\DTOs\RefreshTokenRecord;
use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

class EloquentTenantRefreshTokenRepository implements TenantRefreshTokenRepositoryInterface
{
    public function store(RefreshTokenRecord $record): void
    {
        TenantRefreshTokenModel::query()->create([
            'id' => $record->id->value(),
            'user_id' => $record->userId->value(),
            'token_hash' => $record->tokenHash,
            'parent_id' => $record->parentId?->value(),
            'expires_at' => $record->expiresAt->format('Y-m-d H:i:s'),
            'used_at' => $record->usedAt?->format('Y-m-d H:i:s'),
            'revoked_at' => $record->revokedAt?->format('Y-m-d H:i:s'),
            'ip_address' => $record->ipAddress,
            'user_agent' => $record->userAgent,
            'created_at' => $record->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByTokenHash(string $hash): ?RefreshTokenRecord
    {
        $model = TenantRefreshTokenModel::query()
            ->where('token_hash', $hash)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function markAsUsed(Uuid $id, DateTimeImmutable $usedAt): void
    {
        TenantRefreshTokenModel::query()
            ->where('id', $id->value())
            ->update(['used_at' => $usedAt->format('Y-m-d H:i:s')]);
    }

    public function revokeAllForUser(Uuid $userId): void
    {
        TenantRefreshTokenModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()->format('Y-m-d H:i:s')]);
    }

    public function revokeChain(Uuid $tokenId): void
    {
        /** @var array<string> $visited */
        $visited = [];
        $currentId = $tokenId->value();

        while (! in_array($currentId, $visited, true)) {
            $visited[] = $currentId;

            /** @var string|null $parent */
            $parent = TenantRefreshTokenModel::query()
                ->where('id', $currentId)
                ->value('parent_id');

            if ($parent !== null) {
                $currentId = $parent;
            } else {
                break;
            }
        }

        $rootId = $currentId;
        $idsToRevoke = [$rootId];
        $queue = [$rootId];

        while (! empty($queue)) {
            $parentIds = $queue;
            $queue = [];

            $children = TenantRefreshTokenModel::query()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->all();

            foreach ($children as $childId) {
                if (! in_array($childId, $idsToRevoke, true)) {
                    $idsToRevoke[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        TenantRefreshTokenModel::query()
            ->whereIn('id', $idsToRevoke)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()->format('Y-m-d H:i:s')]);
    }

    private function toDomain(TenantRefreshTokenModel $model): RefreshTokenRecord
    {
        /** @var string $expiresAtRaw */
        $expiresAtRaw = $model->getRawOriginal('expires_at');

        /** @var string|null $usedAtRaw */
        $usedAtRaw = $model->getRawOriginal('used_at');

        /** @var string|null $revokedAtRaw */
        $revokedAtRaw = $model->getRawOriginal('revoked_at');

        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new RefreshTokenRecord(
            id: Uuid::fromString($model->id),
            userId: Uuid::fromString($model->user_id),
            tokenHash: $model->token_hash,
            parentId: $model->parent_id ? Uuid::fromString($model->parent_id) : null,
            expiresAt: new DateTimeImmutable($expiresAtRaw),
            usedAt: $usedAtRaw !== null ? new DateTimeImmutable($usedAtRaw) : null,
            revokedAt: $revokedAtRaw !== null ? new DateTimeImmutable($revokedAtRaw) : null,
            ipAddress: $model->ip_address,
            userAgent: $model->user_agent,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
