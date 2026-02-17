<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Application\Billing\DTOs\NFSeRequestDTO;
use Application\Billing\DTOs\NFSeResultDTO;

interface NFSeProviderInterface
{
    public function emit(NFSeRequestDTO $request): NFSeResultDTO;

    public function cancel(string $providerRef, string $reason): NFSeResultDTO;

    public function getStatus(string $providerRef): NFSeResultDTO;

    public function verifyWebhookSignature(string $payload, string $signature): bool;
}
