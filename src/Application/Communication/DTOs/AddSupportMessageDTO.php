<?php

declare(strict_types=1);

namespace Application\Communication\DTOs;

final readonly class AddSupportMessageDTO
{
    public function __construct(
        public string $supportRequestId,
        public string $senderId,
        public string $body,
        public bool $isInternal,
    ) {}
}
