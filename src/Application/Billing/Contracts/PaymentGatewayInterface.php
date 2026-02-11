<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Application\Billing\DTOs\ChargeRequestDTO;
use Application\Billing\DTOs\ChargeResultDTO;
use Application\Billing\DTOs\RefundRequestDTO;
use Application\Billing\DTOs\RefundResultDTO;

interface PaymentGatewayInterface
{
    public function charge(ChargeRequestDTO $request): ChargeResultDTO;

    public function refund(RefundRequestDTO $request): RefundResultDTO;

    public function verifyWebhookSignature(string $payload, string $signature): bool;
}
