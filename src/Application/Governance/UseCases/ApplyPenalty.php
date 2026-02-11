<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\DTOs\PenaltyDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use DateTimeImmutable;
use Domain\Governance\Entities\Penalty;
use Domain\Governance\Enums\PenaltyType;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ApplyPenalty
{
    public function __construct(
        private PenaltyRepositoryInterface $penaltyRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(
        string $violationId,
        string $unitId,
        string $penaltyType,
        ?int $blockDays,
    ): PenaltyDTO {
        $type = PenaltyType::from($penaltyType);
        $startsAt = new DateTimeImmutable;
        $endsAt = $blockDays !== null
            ? $startsAt->modify("+{$blockDays} days")
            : null;

        $penalty = Penalty::create(
            id: Uuid::generate(),
            violationId: Uuid::fromString($violationId),
            unitId: Uuid::fromString($unitId),
            type: $type,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );

        $this->penaltyRepository->save($penalty);
        $this->eventDispatcher->dispatchAll($penalty->pullDomainEvents());

        return self::toDTO($penalty);
    }

    public static function toDTO(Penalty $penalty): PenaltyDTO
    {
        return new PenaltyDTO(
            id: $penalty->id()->value(),
            violationId: $penalty->violationId()->value(),
            unitId: $penalty->unitId()->value(),
            type: $penalty->type()->value,
            startsAt: $penalty->startsAt()->format('c'),
            endsAt: $penalty->endsAt()?->format('c'),
            status: $penalty->status()->value,
            revokedAt: $penalty->revokedAt()?->format('c'),
            revokedBy: $penalty->revokedBy()?->value(),
            revokedReason: $penalty->revokedReason(),
            createdAt: $penalty->createdAt()->format('c'),
        );
    }
}
