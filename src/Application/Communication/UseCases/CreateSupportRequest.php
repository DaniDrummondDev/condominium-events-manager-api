<?php

declare(strict_types=1);

namespace Application\Communication\UseCases;

use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\CreateSupportRequestDTO;
use Application\Communication\DTOs\SupportRequestDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Communication\Entities\SupportRequest;
use Domain\Communication\Enums\SupportRequestCategory;
use Domain\Communication\Enums\SupportRequestPriority;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CreateSupportRequest
{
    public function __construct(
        private SupportRequestRepositoryInterface $requestRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(CreateSupportRequestDTO $dto): SupportRequestDTO
    {
        $request = SupportRequest::create(
            id: Uuid::generate(),
            userId: Uuid::fromString($dto->userId),
            subject: $dto->subject,
            category: SupportRequestCategory::from($dto->category),
            priority: SupportRequestPriority::from($dto->priority),
        );

        $this->requestRepository->save($request);
        $this->eventDispatcher->dispatchAll($request->pullDomainEvents());

        return self::toDTO($request);
    }

    public static function toDTO(SupportRequest $request): SupportRequestDTO
    {
        return new SupportRequestDTO(
            id: $request->id()->value(),
            userId: $request->userId()->value(),
            subject: $request->subject(),
            category: $request->category()->value,
            status: $request->status()->value,
            priority: $request->priority()->value,
            closedAt: $request->closedAt()?->format('c'),
            closedReason: $request->closedReason()?->value,
            createdAt: $request->createdAt()->format('c'),
            updatedAt: $request->updatedAt()->format('c'),
        );
    }
}
