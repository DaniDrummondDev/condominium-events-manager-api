<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\ViolationContestationRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\ContestationDTO;
use Application\Governance\DTOs\ReviewContestationDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ReviewContestation
{
    public function __construct(
        private ViolationContestationRepositoryInterface $contestationRepository,
        private ViolationRepositoryInterface $violationRepository,
        private EvaluatePenaltyPolicy $evaluatePenaltyPolicy,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(ReviewContestationDTO $dto): ContestationDTO
    {
        $contestation = $this->contestationRepository->findById(Uuid::fromString($dto->contestationId));

        if ($contestation === null) {
            throw new DomainException(
                'Contestation not found',
                'CONTESTATION_NOT_FOUND',
                ['contestation_id' => $dto->contestationId],
            );
        }

        $violation = $this->violationRepository->findById($contestation->violationId());

        if ($violation === null) {
            throw new DomainException(
                'Violation not found',
                'VIOLATION_NOT_FOUND',
                ['violation_id' => $contestation->violationId()->value()],
            );
        }

        $respondedBy = Uuid::fromString($dto->respondedBy);

        if ($dto->accepted) {
            // Accept contestation → revoke violation
            $contestation->accept($respondedBy, $dto->response);
            $violation->revoke($respondedBy, 'Contestation accepted: '.$dto->response);
        } else {
            // Reject contestation → uphold violation + evaluate penalty
            $contestation->reject($respondedBy, $dto->response);
            $violation->uphold($respondedBy);
        }

        $this->contestationRepository->save($contestation);
        $this->violationRepository->save($violation);
        $this->eventDispatcher->dispatchAll($violation->pullDomainEvents());

        // If rejected, evaluate penalty policies
        if (! $dto->accepted) {
            $this->evaluatePenaltyPolicy->execute(
                $violation->id()->value(),
                $violation->unitId()->value(),
                $violation->type()->value,
            );
        }

        return ContestViolation::toDTO($contestation);
    }
}
