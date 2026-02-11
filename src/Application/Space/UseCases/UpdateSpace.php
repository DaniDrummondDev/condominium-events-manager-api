<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\SpaceDTO;
use Application\Space\DTOs\UpdateSpaceDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceType;

final readonly class UpdateSpace
{
    public function __construct(
        private SpaceRepositoryInterface $spaceRepository,
    ) {}

    public function execute(UpdateSpaceDTO $dto): SpaceDTO
    {
        $space = $this->spaceRepository->findById(Uuid::fromString($dto->spaceId));

        if ($space === null) {
            throw new DomainException(
                'Space not found',
                'SPACE_NOT_FOUND',
                ['space_id' => $dto->spaceId],
            );
        }

        if ($dto->name !== null) {
            $existing = $this->spaceRepository->findByName($dto->name);

            if ($existing !== null && $existing->id()->value() !== $dto->spaceId) {
                throw new DomainException(
                    "Space with name '{$dto->name}' already exists",
                    'SPACE_NAME_DUPLICATE',
                    ['name' => $dto->name],
                );
            }

            $space->updateName($dto->name);
        }

        if ($dto->description !== null) {
            $space->updateDescription($dto->description);
        }

        if ($dto->type !== null) {
            $space->updateType(SpaceType::from($dto->type));
        }

        if ($dto->capacity !== null) {
            $space->updateCapacity($dto->capacity);
        }

        if ($dto->requiresApproval !== null) {
            $space->updateRequiresApproval($dto->requiresApproval);
        }

        if ($dto->maxDurationHours !== null) {
            $space->updateMaxDurationHours($dto->maxDurationHours);
        }

        if ($dto->maxAdvanceDays !== null) {
            $space->updateMaxAdvanceDays($dto->maxAdvanceDays);
        }

        if ($dto->minAdvanceHours !== null) {
            $space->updateMinAdvanceHours($dto->minAdvanceHours);
        }

        if ($dto->cancellationDeadlineHours !== null) {
            $space->updateCancellationDeadlineHours($dto->cancellationDeadlineHours);
        }

        $this->spaceRepository->save($space);

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
