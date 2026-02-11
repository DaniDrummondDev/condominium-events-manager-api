<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\ViolationContestationRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\ContestationDTO;
use Application\Governance\DTOs\ContestViolationDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Entities\ViolationContestation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ContestViolation
{
    public function __construct(
        private ViolationRepositoryInterface $violationRepository,
        private ViolationContestationRepositoryInterface $contestationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(ContestViolationDTO $dto): ContestationDTO
    {
        $violation = $this->violationRepository->findById(Uuid::fromString($dto->violationId));

        if ($violation === null) {
            throw new DomainException(
                'Violation not found',
                'VIOLATION_NOT_FOUND',
                ['violation_id' => $dto->violationId],
            );
        }

        // Check if already contested
        $existingContestation = $this->contestationRepository->findByViolation($violation->id());
        if ($existingContestation !== null) {
            throw DomainException::businessRule(
                'VIOLATION_ALREADY_CONTESTED',
                'This violation already has a contestation',
                ['violation_id' => $dto->violationId],
            );
        }

        // Transition violation to contested
        $violation->contest();

        // Create contestation record
        $contestation = ViolationContestation::create(
            id: Uuid::generate(),
            violationId: $violation->id(),
            tenantUserId: Uuid::fromString($dto->tenantUserId),
            reason: $dto->reason,
        );

        $this->violationRepository->save($violation);
        $this->contestationRepository->save($contestation);
        $this->eventDispatcher->dispatchAll($violation->pullDomainEvents());

        return self::toDTO($contestation);
    }

    public static function toDTO(ViolationContestation $contestation): ContestationDTO
    {
        return new ContestationDTO(
            id: $contestation->id()->value(),
            violationId: $contestation->violationId()->value(),
            tenantUserId: $contestation->tenantUserId()->value(),
            reason: $contestation->reason(),
            status: $contestation->status()->value,
            response: $contestation->response(),
            respondedBy: $contestation->respondedBy()?->value(),
            respondedAt: $contestation->respondedAt()?->format('c'),
            createdAt: $contestation->createdAt()->format('c'),
        );
    }
}
