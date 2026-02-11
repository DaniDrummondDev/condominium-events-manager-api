<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class DeactivateSpace
{
    public function __construct(
        private SpaceRepositoryInterface $spaceRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $spaceId): void
    {
        $space = $this->spaceRepository->findById(Uuid::fromString($spaceId));

        if ($space === null) {
            throw new DomainException(
                'Space not found',
                'SPACE_NOT_FOUND',
                ['space_id' => $spaceId],
            );
        }

        $space->deactivate();

        $this->spaceRepository->save($space);
        $this->eventDispatcher->dispatchAll($space->pullDomainEvents());
    }
}
