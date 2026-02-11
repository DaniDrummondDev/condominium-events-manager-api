<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class WebhookPayloadDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gateway,
        public string $eventType,
        public string $gatewayTransactionId,
        public string $status,
        public int $amountInCents,
        public array $metadata = [],
    ) {}
}
