<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Application\Space\DTOs\ConfigureSpaceRuleDTO;
use Application\Space\DTOs\SpaceRuleDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceRule;

final readonly class ConfigureSpaceRules
{
    public function __construct(
        private SpaceRepositoryInterface $spaceRepository,
        private SpaceRuleRepositoryInterface $ruleRepository,
    ) {}

    public function execute(ConfigureSpaceRuleDTO $dto): SpaceRuleDTO
    {
        $spaceId = Uuid::fromString($dto->spaceId);
        $space = $this->spaceRepository->findById($spaceId);

        if ($space === null) {
            throw new DomainException(
                'Space not found',
                'SPACE_NOT_FOUND',
                ['space_id' => $dto->spaceId],
            );
        }

        $existing = $this->ruleRepository->findBySpaceIdAndKey($spaceId, $dto->ruleKey);

        if ($existing !== null) {
            $existing->updateValue($dto->ruleValue);

            if ($dto->description !== null) {
                $existing->updateDescription($dto->description);
            }

            $this->ruleRepository->save($existing);

            return new SpaceRuleDTO(
                id: $existing->id()->value(),
                spaceId: $existing->spaceId()->value(),
                ruleKey: $existing->ruleKey(),
                ruleValue: $existing->ruleValue(),
                description: $existing->description(),
            );
        }

        $rule = SpaceRule::create(
            Uuid::generate(),
            $spaceId,
            $dto->ruleKey,
            $dto->ruleValue,
            $dto->description,
        );

        $this->ruleRepository->save($rule);

        return new SpaceRuleDTO(
            id: $rule->id()->value(),
            spaceId: $rule->spaceId()->value(),
            ruleKey: $rule->ruleKey(),
            ruleValue: $rule->ruleValue(),
            description: $rule->description(),
        );
    }
}
