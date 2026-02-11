<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class CreateFeatureOverrideDTO
{
    public function __construct(
        public string $tenantId,
        public string $featureId,
        public string $value,
        public string $reason,
        public ?string $expiresAt = null,
        public ?string $createdBy = null,
    ) {}
}
