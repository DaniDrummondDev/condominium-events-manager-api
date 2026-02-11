<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use Application\Governance\DTOs\RuleDTO;
use Application\Governance\DTOs\UpdateRuleDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class UpdateRule
{
    public function __construct(
        private CondominiumRuleRepositoryInterface $ruleRepository,
    ) {}

    public function execute(UpdateRuleDTO $dto): RuleDTO
    {
        $rule = $this->ruleRepository->findById(Uuid::fromString($dto->ruleId));

        if ($rule === null) {
            throw new DomainException(
                'Rule not found',
                'RULE_NOT_FOUND',
                ['rule_id' => $dto->ruleId],
            );
        }

        if ($dto->title !== null) {
            $rule->updateTitle($dto->title);
        }

        if ($dto->description !== null) {
            $rule->updateDescription($dto->description);
        }

        if ($dto->category !== null) {
            $rule->updateCategory($dto->category);
        }

        if ($dto->order !== null) {
            $rule->updateOrder($dto->order);
        }

        $this->ruleRepository->save($rule);

        return CreateRule::toDTO($rule);
    }
}
