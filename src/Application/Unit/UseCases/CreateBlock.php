<?php

declare(strict_types=1);

namespace Application\Unit\UseCases;

use App\Infrastructure\MultiTenancy\TenantContext;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\BlockRepositoryInterface;
use Application\Unit\DTOs\BlockDTO;
use Application\Unit\DTOs\CreateBlockDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Unit\Entities\Block;

final readonly class CreateBlock
{
    public function __construct(
        private BlockRepositoryInterface $blockRepository,
        private EventDispatcherInterface $eventDispatcher,
        private TenantContext $tenantContext,
    ) {}

    public function execute(CreateBlockDTO $dto): BlockDTO
    {
        $condoType = CondominiumType::from($this->tenantContext->tenantType);

        if ($condoType === CondominiumType::Horizontal) {
            throw new DomainException(
                'Horizontal condominiums do not support blocks',
                'BLOCK_NOT_SUPPORTED',
                ['condominium_type' => $condoType->value],
            );
        }

        $existing = $this->blockRepository->findByIdentifier($dto->identifier);

        if ($existing !== null) {
            throw new DomainException(
                "Block with identifier '{$dto->identifier}' already exists",
                'BLOCK_IDENTIFIER_DUPLICATE',
                ['identifier' => $dto->identifier],
            );
        }

        $block = Block::create(
            Uuid::generate(),
            $dto->name,
            $dto->identifier,
            $dto->floors,
            $this->tenantContext->tenantId,
        );

        $this->blockRepository->save($block);
        $this->eventDispatcher->dispatchAll($block->pullDomainEvents());

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
