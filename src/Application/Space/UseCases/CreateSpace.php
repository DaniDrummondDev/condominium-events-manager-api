<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use App\Infrastructure\MultiTenancy\TenantContext;
use Application\Billing\Contracts\FeatureResolverInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\CreateSpaceDTO;
use Application\Space\DTOs\SpaceDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceType;

final readonly class CreateSpace
{
    public function __construct(
        private SpaceRepositoryInterface $spaceRepository,
        private FeatureResolverInterface $featureResolver,
        private EventDispatcherInterface $eventDispatcher,
        private TenantContext $tenantContext,
    ) {}

    public function execute(CreateSpaceDTO $dto): SpaceDTO
    {
        $tenantId = Uuid::fromString($this->tenantContext->tenantId);

        $maxSpaces = $this->featureResolver->featureLimit($tenantId, 'max_spaces');

        if ($maxSpaces > 0) {
            $currentCount = $this->spaceRepository->countActiveByTenant();

            if ($currentCount >= $maxSpaces) {
                throw new DomainException(
                    "Space limit reached ({$maxSpaces})",
                    'SPACE_LIMIT_REACHED',
                    ['max_spaces' => $maxSpaces, 'current_count' => $currentCount],
                );
            }
        }

        $existing = $this->spaceRepository->findByName($dto->name);

        if ($existing !== null) {
            throw new DomainException(
                "Space with name '{$dto->name}' already exists",
                'SPACE_NAME_DUPLICATE',
                ['name' => $dto->name],
            );
        }

        $space = Space::create(
            Uuid::generate(),
            $dto->name,
            $dto->description,
            SpaceType::from($dto->type),
            $dto->capacity,
            $dto->requiresApproval,
            $dto->maxDurationHours,
            $dto->maxAdvanceDays,
            $dto->minAdvanceHours,
            $dto->cancellationDeadlineHours,
        );

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
