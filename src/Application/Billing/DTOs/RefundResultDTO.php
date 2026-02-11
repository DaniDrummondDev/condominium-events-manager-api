<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class RefundResultDTO
{
    public function __construct(
        public bool $success,
        public ?string $refundId = null,
        public ?string $errorMessage = null,
    ) {}
}
