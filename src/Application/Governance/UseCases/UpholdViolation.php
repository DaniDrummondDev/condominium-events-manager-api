<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\ViolationDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class UpholdViolation
{
    public function __construct(
        private ViolationRepositoryInterface $violationRepository,
        private EvaluatePenaltyPolicy $evaluatePenaltyPolicy,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $violationId, string $upheldBy): ViolationDTO
    {
        $violation = $this->violationRepository->findById(Uuid::fromString($violationId));

        if ($violation === null) {
            throw new DomainException(
                'Violation not found',
                'VIOLATION_NOT_FOUND',
                ['violation_id' => $violationId],
            );
        }

        $violation->uphold(Uuid::fromString($upheldBy));

        $this->violationRepository->save($violation);
        $this->eventDispatcher->dispatchAll($violation->pullDomainEvents());

        // Evaluate penalty policies after upholding
        $this->evaluatePenaltyPolicy->execute(
            $violation->id()->value(),
            $violation->unitId()->value(),
            $violation->type()->value,
        );

        return RegisterViolation::toDTO($violation);
    }
}
