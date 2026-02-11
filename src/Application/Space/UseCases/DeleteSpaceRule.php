<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class DeleteSpaceRule
{
    public function __construct(
        private SpaceRuleRepositoryInterface $ruleRepository,
    ) {}

    public function execute(string $ruleId): void
    {
        $id = Uuid::fromString($ruleId);
        $rule = $this->ruleRepository->findById($id);

        if ($rule === null) {
            throw new DomainException(
                'Space rule not found',
                'SPACE_RULE_NOT_FOUND',
                ['rule_id' => $ruleId],
            );
        }

        $this->ruleRepository->delete($id);
    }
}
