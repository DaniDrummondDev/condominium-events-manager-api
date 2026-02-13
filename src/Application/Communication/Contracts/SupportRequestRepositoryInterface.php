<?php

declare(strict_types=1);

namespace Application\Communication\Contracts;

use Domain\Communication\Entities\SupportRequest;
use Domain\Shared\ValueObjects\Uuid;

interface SupportRequestRepositoryInterface
{
    public function findById(Uuid $id): ?SupportRequest;

    /**
     * @return array<SupportRequest>
     */
    public function findByUser(Uuid $userId): array;

    /**
     * @return array<SupportRequest>
     */
    public function findAll(): array;

    public function save(SupportRequest $request): void;
}
