<?php

declare(strict_types=1);

namespace Application\Communication\DTOs;

final readonly class SupportMessageDTO
{
    public function __construct(
        public string $id,
        public string $supportRequestId,
        public string $senderId,
        public string $body,
        public bool $isInternal,
        public string $createdAt,
    ) {}
}
