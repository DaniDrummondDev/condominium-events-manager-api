<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

use Domain\Auth\Entities\PlatformUser;
use Domain\Shared\ValueObjects\Uuid;

interface PlatformUserRepositoryInterface
{
    public function findByEmail(string $email): ?PlatformUser;

    public function findById(Uuid $id): ?PlatformUser;

    public function save(PlatformUser $user): void;
}
