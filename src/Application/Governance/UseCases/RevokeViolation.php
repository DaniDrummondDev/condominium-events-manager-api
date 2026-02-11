<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\ViolationDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RevokeViolation
{
    public function __construct(
        private ViolationRepositoryInterface $violationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $violationId, string $revokedBy, string $reason): ViolationDTO
    {
        $violation = $this->violationRepository->findById(Uuid::fromString($violationId));

        if ($violation === null) {
            throw new DomainException(
                'Violation not found',
                'VIOLATION_NOT_FOUND',
                ['violation_id' => $violationId],
            );
        }

        $violation->revoke(Uuid::fromString($revokedBy), $reason);

        $this->violationRepository->save($violation);
        $this->eventDispatcher->dispatchAll($violation->pullDomainEvents());

        return RegisterViolation::toDTO($violation);
    }
}
