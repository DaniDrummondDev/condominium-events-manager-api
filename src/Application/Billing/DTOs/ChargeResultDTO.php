<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class ChargeResultDTO
{
    public function __construct(
        public bool $success,
        public ?string $gatewayTransactionId = null,
        public ?string $status = null,
        public ?string $errorMessage = null,
    ) {}
}
