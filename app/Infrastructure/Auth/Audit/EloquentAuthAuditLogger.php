<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Audit;

use App\Infrastructure\Persistence\Platform\Models\PlatformAuditLogModel;
use Application\Auth\Contracts\AuthAuditLoggerInterface;
use Application\Auth\DTOs\AuthAuditEntry;

class EloquentAuthAuditLogger implements AuthAuditLoggerInterface
{
    public function log(AuthAuditEntry $entry): void
    {
        PlatformAuditLogModel::query()->create([
            'actor_type' => 'user',
            'actor_id' => $entry->actorId?->value() ?? '00000000-0000-0000-0000-000000000000',
            'action' => $entry->eventType->value,
            'resource_type' => 'auth',
            'resource_id' => $entry->tenantId?->value(),
            'context' => array_merge($entry->metadata, [
                'user_agent' => $entry->userAgent,
            ]),
            'ip_address' => $entry->ipAddress,
            'created_at' => $entry->occurredAt->format('Y-m-d H:i:s'),
        ]);
    }
}
