<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\SpaceDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceStatus;

final readonly class ChangeSpaceStatus
{
    public function __construct(
        private SpaceRepositoryInterface $spaceRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $spaceId, string $status): SpaceDTO
    {
        $space = $this->spaceRepository->findById(Uuid::fromString($spaceId));

        if ($space === null) {
            throw new DomainException(
                'Space not found',
                'SPACE_NOT_FOUND',
                ['space_id' => $spaceId],
            );
        }

        $newStatus = SpaceStatus::from($status);

        match ($newStatus) {
            SpaceStatus::Active => $space->activate(),
            SpaceStatus::Inactive => $space->deactivate(),
            SpaceStatus::Maintenance => $space->setMaintenance(),
        };

        $this->spaceRepository->save($space);
        $this->eventDispatcher->dispatchAll($space->pullDomainEvents());

        return $this->toDTO($space);
    }

    private function toDTO(Space $space): SpaceDTO
    {
        return new SpaceDTO(
            id: $space->id()->value(),
            name: $space->name(),
            description: $space->description(),
            type: $space->type()->value,
            status: $space->status()->value,
            capacity: $space->capacity(),
            requiresApproval: $space->requiresApproval(),
            maxDurationHours: $space->maxDurationHours(),
            maxAdvanceDays: $space->maxAdvanceDays(),
            minAdvanceHours: $space->minAdvanceHours(),
            cancellationDeadlineHours: $space->cancellationDeadlineHours(),
            createdAt: $space->createdAt()->format('c'),
        );
    }
}
