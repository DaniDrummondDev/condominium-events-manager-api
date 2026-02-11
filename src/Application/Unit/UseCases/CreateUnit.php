<?php

declare(strict_types=1);

namespace Application\Unit\UseCases;

use App\Infrastructure\MultiTenancy\TenantContext;
use Application\Billing\Contracts\FeatureResolverInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\BlockRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Application\Unit\DTOs\CreateUnitDTO;
use Application\Unit\DTOs\UnitDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Unit\Entities\Unit;
use Domain\Unit\Enums\UnitType;

final readonly class CreateUnit
{
    public function __construct(
        private UnitRepositoryInterface $unitRepository,
        private BlockRepositoryInterface $blockRepository,
        private FeatureResolverInterface $featureResolver,
        private EventDispatcherInterface $eventDispatcher,
        private TenantContext $tenantContext,
    ) {}

    public function execute(CreateUnitDTO $dto): UnitDTO
    {
        $tenantId = Uuid::fromString($this->tenantContext->tenantId);
        $condoType = CondominiumType::from($this->tenantContext->tenantType);
        $blockId = $dto->blockId !== null ? Uuid::fromString($dto->blockId) : null;

        if ($condoType === CondominiumType::Vertical && $blockId === null) {
            throw new DomainException(
                'Vertical condominiums require a block for each unit',
                'UNIT_BLOCK_REQUIRED',
                ['condominium_type' => $condoType->value],
            );
        }

        if ($blockId !== null) {
            $block = $this->blockRepository->findById($blockId);

            if ($block === null) {
                throw new DomainException(
                    'Block not found',
                    'BLOCK_NOT_FOUND',
                    ['block_id' => $dto->blockId],
                );
            }
        }

        $maxUnits = $this->featureResolver->featureLimit($tenantId, 'max_units');

        if ($maxUnits > 0) {
            $currentCount = $this->unitRepository->countActiveByTenant();

            if ($currentCount >= $maxUnits) {
                throw new DomainException(
                    "Unit limit reached ({$maxUnits})",
                    'UNIT_LIMIT_REACHED',
                    ['max_units' => $maxUnits, 'current_count' => $currentCount],
                );
            }
        }

        $existing = $this->unitRepository->findByNumber($dto->number, $blockId);

        if ($existing !== null) {
            throw new DomainException(
                "Unit with number '{$dto->number}' already exists",
                'UNIT_NUMBER_DUPLICATE',
                ['number' => $dto->number, 'block_id' => $dto->blockId],
            );
        }

        $unit = Unit::create(
            Uuid::generate(),
            $blockId,
            $dto->number,
            $dto->floor,
            UnitType::from($dto->type),
        );

        $this->unitRepository->save($unit);
        $this->eventDispatcher->dispatchAll($unit->pullDomainEvents());

        return new UnitDTO(
            id: $unit->id()->value(),
            blockId: $unit->blockId()?->value(),
            number: $unit->number(),
            floor: $unit->floor(),
            type: $unit->type()->value,
            status: $unit->status()->value,
            isOccupied: $unit->isOccupied(),
            createdAt: $unit->createdAt()->format('c'),
        );
    }
}
