<?php

declare(strict_types=1);

namespace Application\Communication\Contracts;

use Domain\Communication\Entities\SupportMessage;
use Domain\Shared\ValueObjects\Uuid;

interface SupportMessageRepositoryInterface
{
    /**
     * @return array<SupportMessage>
     */
    public function findByRequest(Uuid $supportRequestId): array;

    public function save(SupportMessage $message): void;
}
