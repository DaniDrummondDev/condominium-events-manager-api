<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

use DateTimeImmutable;

final readonly class GatewayEventRecord
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $gateway,
        public string $eventType,
        public array $payload,
        public string $idempotencyKey,
        public ?DateTimeImmutable $processedAt = null,
    ) {}
}
