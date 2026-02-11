<?php

declare(strict_types=1);

namespace Application\Space\DTOs;

final readonly class SpaceRuleDTO
{
    public function __construct(
        public string $id,
        public string $spaceId,
        public string $ruleKey,
        public string $ruleValue,
        public ?string $description,
    ) {}
}
