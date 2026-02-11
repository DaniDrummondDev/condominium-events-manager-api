<?php

declare(strict_types=1);

namespace Application\Unit\UseCases;

use Application\Unit\Contracts\BlockRepositoryInterface;
use Application\Unit\DTOs\BlockDTO;
use Application\Unit\DTOs\UpdateBlockDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class UpdateBlock
{
    public function __construct(
        private BlockRepositoryInterface $blockRepository,
    ) {}

    public function execute(UpdateBlockDTO $dto): BlockDTO
    {
        $block = $this->blockRepository->findById(Uuid::fromString($dto->blockId));

        if ($block === null) {
            throw new DomainException(
                'Block not found',
                'BLOCK_NOT_FOUND',
                ['block_id' => $dto->blockId],
            );
        }

        if ($dto->identifier !== null && $dto->identifier !== $block->identifier()) {
            $existing = $this->blockRepository->findByIdentifier($dto->identifier);

            if ($existing !== null) {
                throw new DomainException(
                    "Block with identifier '{$dto->identifier}' already exists",
                    'BLOCK_IDENTIFIER_DUPLICATE',
                    ['identifier' => $dto->identifier],
                );
            }

            $block->updateIdentifier($dto->identifier);
        }

        if ($dto->name !== null) {
            $block->rename($dto->name);
        }

        if ($dto->floors !== null) {
            $block->updateFloors($dto->floors);
        }

        $this->blockRepository->save($block);

        return new BlockDTO(
            id: $block->id()->value(),
            name: $block->name(),
            identifier: $block->identifier(),
            floors: $block->floors(),
            status: $block->status()->value,
            createdAt: $block->createdAt()->format('c'),
        );
    }
}
