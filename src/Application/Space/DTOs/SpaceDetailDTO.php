<?php

declare(strict_types=1);

namespace Application\Space\DTOs;

final readonly class SpaceDetailDTO
{
    /**
     * @param  array<SpaceAvailabilityDTO>  $availabilities
     * @param  array<SpaceBlockDTO>  $blocks
     * @param  array<SpaceRuleDTO>  $rules
     */
    public function __construct(
        public SpaceDTO $space,
        public array $availabilities,
        public array $blocks,
        public array $rules,
    ) {}
}
