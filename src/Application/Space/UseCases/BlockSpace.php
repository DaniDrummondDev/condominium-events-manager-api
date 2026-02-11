<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\BlockSpaceDTO;
use Application\Space\DTOs\SpaceBlockDTO;
use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceBlock;
use Domain\Space\Events\SpaceBlocked;

final readonly class BlockSpace
{
    public function __construct(
        private SpaceRepositoryInterface $spaceRepository,
        private SpaceBlockRepositoryInterface $blockRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(BlockSpaceDTO $dto): SpaceBlockDTO
    {
        $spaceId = Uuid::fromString($dto->spaceId);
        $space = $this->spaceRepository->findById($spaceId);

        if ($space === null) {
            throw new DomainException(
                'Space not found',
                'SPACE_NOT_FOUND',
                ['space_id' => $dto->spaceId],
            );
        }

        $block = SpaceBlock::create(
            Uuid::generate(),
            $spaceId,
            $dto->reason,
            new DateTimeImmutable($dto->startDatetime),
            new DateTimeImmutable($dto->endDatetime),
            Uuid::fromString($dto->blockedBy),
            $dto->notes,
        );

        $this->blockRepository->save($block);

        $this->eventDispatcher->dispatchAll([
            new SpaceBlocked(
                $dto->spaceId,
                $block->id()->value(),
                $dto->reason,
                $block->startDatetime()->format('c'),
                $block->endDatetime()->format('c'),
            ),
        ]);

        return new SpaceBlockDTO(
            id: $block->id()->value(),
            spaceId: $block->spaceId()->value(),
            reason: $block->reason(),
            startDatetime: $block->startDatetime()->format('c'),
            endDatetime: $block->endDatetime()->format('c'),
            blockedBy: $block->blockedBy()->value(),
            notes: $block->notes(),
            createdAt: $block->createdAt()->format('c'),
        );
    }
}
