<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class RefundRequestDTO
{
    public function __construct(
        public string $gatewayTransactionId,
        public int $amountInCents,
        public string $reason,
    ) {}
}
