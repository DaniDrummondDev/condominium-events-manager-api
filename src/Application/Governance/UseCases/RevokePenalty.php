<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\DTOs\PenaltyDTO;
use Application\Governance\DTOs\RevokePenaltyDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RevokePenalty
{
    public function __construct(
        private PenaltyRepositoryInterface $penaltyRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(RevokePenaltyDTO $dto): PenaltyDTO
    {
        $penalty = $this->penaltyRepository->findById(Uuid::fromString($dto->penaltyId));

        if ($penalty === null) {
            throw new DomainException(
                'Penalty not found',
                'PENALTY_NOT_FOUND',
                ['penalty_id' => $dto->penaltyId],
            );
        }

        $penalty->revoke(Uuid::fromString($dto->revokedBy), $dto->reason);

        $this->penaltyRepository->save($penalty);
        $this->eventDispatcher->dispatchAll($penalty->pullDomainEvents());

        return ApplyPenalty::toDTO($penalty);
    }
}
