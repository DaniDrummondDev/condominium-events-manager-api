<?php

declare(strict_types=1);

namespace Application\Space\Contracts;

use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceRule;

interface SpaceRuleRepositoryInterface
{
    /**
     * @return array<SpaceRule>
     */
    public function findBySpaceId(Uuid $spaceId): array;

    public function findBySpaceIdAndKey(Uuid $spaceId, string $ruleKey): ?SpaceRule;

    public function findById(Uuid $id): ?SpaceRule;

    public function save(SpaceRule $rule): void;

    public function delete(Uuid $id): void;
}
