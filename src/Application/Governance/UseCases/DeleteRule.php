<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class DeleteRule
{
    public function __construct(
        private CondominiumRuleRepositoryInterface $ruleRepository,
    ) {}

    public function execute(string $ruleId): void
    {
        $rule = $this->ruleRepository->findById(Uuid::fromString($ruleId));

        if ($rule === null) {
            throw new DomainException(
                'Rule not found',
                'RULE_NOT_FOUND',
                ['rule_id' => $ruleId],
            );
        }

        $this->ruleRepository->delete($rule->id());
    }
}
