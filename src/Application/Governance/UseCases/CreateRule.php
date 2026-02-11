<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use Application\Governance\DTOs\CreateRuleDTO;
use Application\Governance\DTOs\RuleDTO;
use Domain\Governance\Entities\CondominiumRule;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CreateRule
{
    public function __construct(
        private CondominiumRuleRepositoryInterface $ruleRepository,
    ) {}

    public function execute(CreateRuleDTO $dto): RuleDTO
    {
        $rule = CondominiumRule::create(
            id: Uuid::generate(),
            title: $dto->title,
            description: $dto->description,
            category: $dto->category,
            order: $dto->order,
            createdBy: Uuid::fromString($dto->createdBy),
        );

        $this->ruleRepository->save($rule);

        return self::toDTO($rule);
    }

    public static function toDTO(CondominiumRule $rule): RuleDTO
    {
        return new RuleDTO(
            id: $rule->id()->value(),
            title: $rule->title(),
            description: $rule->description(),
            category: $rule->category(),
            isActive: $rule->isActive(),
            order: $rule->order(),
            createdBy: $rule->createdBy()->value(),
            createdAt: $rule->createdAt()->format('c'),
        );
    }
}
