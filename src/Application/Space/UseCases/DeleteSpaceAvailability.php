<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class DeleteSpaceAvailability
{
    public function __construct(
        private SpaceAvailabilityRepositoryInterface $availabilityRepository,
    ) {}

    public function execute(string $availabilityId): void
    {
        $id = Uuid::fromString($availabilityId);
        $availability = $this->availabilityRepository->findById($id);

        if ($availability === null) {
            throw new DomainException(
                'Availability not found',
                'AVAILABILITY_NOT_FOUND',
                ['availability_id' => $availabilityId],
            );
        }

        $this->availabilityRepository->delete($id);
    }
}
