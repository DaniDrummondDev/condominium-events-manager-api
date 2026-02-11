<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\RegisterViolationDTO;
use Application\Governance\DTOs\ViolationDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Entities\Violation;
use Domain\Governance\Enums\ViolationSeverity;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RegisterViolation
{
    public function __construct(
        private ViolationRepositoryInterface $violationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(RegisterViolationDTO $dto): ViolationDTO
    {
        $type = ViolationType::from($dto->type);
        $severity = ViolationSeverity::from($dto->severity);

        $violation = Violation::create(
            id: Uuid::generate(),
            unitId: Uuid::fromString($dto->unitId),
            tenantUserId: $dto->tenantUserId !== null ? Uuid::fromString($dto->tenantUserId) : null,
            reservationId: $dto->reservationId !== null ? Uuid::fromString($dto->reservationId) : null,
            ruleId: $dto->ruleId !== null ? Uuid::fromString($dto->ruleId) : null,
            type: $type,
            severity: $severity,
            description: $dto->description,
            createdBy: Uuid::fromString($dto->createdBy),
        );

        $this->violationRepository->save($violation);
        $this->eventDispatcher->dispatchAll($violation->pullDomainEvents());

        return self::toDTO($violation);
    }

    public static function toDTO(Violation $violation): ViolationDTO
    {
        return new ViolationDTO(
            id: $violation->id()->value(),
            unitId: $violation->unitId()->value(),
            tenantUserId: $violation->tenantUserId()?->value(),
            reservationId: $violation->reservationId()?->value(),
            ruleId: $violation->ruleId()?->value(),
            type: $violation->type()->value,
            severity: $violation->severity()->value,
            description: $violation->description(),
            status: $violation->status()->value,
            isAutomatic: $violation->isAutomatic(),
            createdBy: $violation->createdBy()?->value(),
            upheldBy: $violation->upheldBy()?->value(),
            upheldAt: $violation->upheldAt()?->format('c'),
            revokedBy: $violation->revokedBy()?->value(),
            revokedAt: $violation->revokedAt()?->format('c'),
            revokedReason: $violation->revokedReason(),
            createdAt: $violation->createdAt()->format('c'),
        );
    }
}
