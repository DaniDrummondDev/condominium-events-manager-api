<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class UnblockSpace
{
    public function __construct(
        private SpaceBlockRepositoryInterface $blockRepository,
    ) {}

    public function execute(string $blockId): void
    {
        $id = Uuid::fromString($blockId);
        $block = $this->blockRepository->findById($id);

        if ($block === null) {
            throw new DomainException(
                'Space block not found',
                'SPACE_BLOCK_NOT_FOUND',
                ['block_id' => $blockId],
            );
        }

        $this->blockRepository->delete($id);
    }
}
