<?php

declare(strict_types=1);

namespace Application\Communication\UseCases;

use Application\Communication\Contracts\SupportMessageRepositoryInterface;
use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\AddSupportMessageDTO;
use Application\Communication\DTOs\SupportMessageDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Communication\Entities\SupportMessage;
use Domain\Communication\Events\SupportMessageSent;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ReplySupportRequest
{
    public function __construct(
        private SupportRequestRepositoryInterface $requestRepository,
        private SupportMessageRepositoryInterface $messageRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(AddSupportMessageDTO $dto): SupportMessageDTO
    {
        $request = $this->requestRepository->findById(Uuid::fromString($dto->supportRequestId));

        if ($request === null) {
            throw new DomainException(
                'Support request not found',
                'SUPPORT_REQUEST_NOT_FOUND',
                ['support_request_id' => $dto->supportRequestId],
            );
        }

        if ($request->status()->isTerminal()) {
            throw new DomainException(
                'Cannot reply to a closed support request',
                'SUPPORT_REQUEST_CLOSED',
                [
                    'support_request_id' => $dto->supportRequestId,
                    'current_status' => $request->status()->value,
                ],
            );
        }

        if ($request->status()->value === 'open') {
            $request->startProgress();
            $this->requestRepository->save($request);
            $this->eventDispatcher->dispatchAll($request->pullDomainEvents());
        }

        $message = SupportMessage::create(
            id: Uuid::generate(),
            supportRequestId: Uuid::fromString($dto->supportRequestId),
            senderId: Uuid::fromString($dto->senderId),
            body: $dto->body,
            isInternal: $dto->isInternal,
        );

        $this->messageRepository->save($message);

        $this->eventDispatcher->dispatchAll([
            new SupportMessageSent(
                $message->id()->value(),
                $dto->supportRequestId,
                $dto->senderId,
                $dto->isInternal,
            ),
        ]);

        return self::toDTO($message);
    }

    public static function toDTO(SupportMessage $message): SupportMessageDTO
    {
        return new SupportMessageDTO(
            id: $message->id()->value(),
            supportRequestId: $message->supportRequestId()->value(),
            senderId: $message->senderId()->value(),
            body: $message->body(),
            isInternal: $message->isInternal(),
            createdAt: $message->createdAt()->format('c'),
        );
    }
}
