<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

use Domain\Auth\Entities\TenantUser;
use Domain\Shared\ValueObjects\Uuid;

interface TenantUserRepositoryInterface
{
    public function findByEmail(string $email): ?TenantUser;

    public function findById(Uuid $id): ?TenantUser;

    public function save(TenantUser $user): void;
}
