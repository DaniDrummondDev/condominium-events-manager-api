<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Application\Billing\DTOs\GatewayEventRecord;

interface GatewayEventRepositoryInterface
{
    public function findByIdempotencyKey(string $idempotencyKey): ?GatewayEventRecord;

    public function save(GatewayEventRecord $record): void;
}
