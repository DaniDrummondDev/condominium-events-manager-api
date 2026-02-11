<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\DunningPolicy;
use Domain\Shared\ValueObjects\Uuid;

interface DunningPolicyRepositoryInterface
{
    public function findDefault(): ?DunningPolicy;

    public function findById(Uuid $id): ?DunningPolicy;

    public function save(DunningPolicy $policy): void;
}
