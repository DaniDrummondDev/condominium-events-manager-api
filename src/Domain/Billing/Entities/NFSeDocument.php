<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use DateTimeImmutable;
use Domain\Billing\Enums\NFSeStatus;
use Domain\Billing\Events\NFSeAuthorized;
use Domain\Billing\Events\NFSeCancelled;
use Domain\Billing\Events\NFSeDenied;
use Domain\Billing\Events\NFSeRequested;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class NFSeDocument
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $tenantId,
        private readonly Uuid $invoiceId,
        private NFSeStatus $status,
        private ?string $providerRef,
        private ?string $nfseNumber,
        private ?string $verificationCode,
        private readonly string $serviceDescription,
        private readonly DateTimeImmutable $competenceDate,
        private readonly Money $totalAmount,
        private readonly float $issRate,
        private readonly Money $issAmount,
        private ?string $pdfUrl,
        private ?string $xmlContent,
        private ?array $providerResponse,
        private ?DateTimeImmutable $authorizedAt,
        private ?DateTimeImmutable $cancelledAt,
        private ?string $errorMessage,
        private readonly string $idempotencyKey,
        private readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $tenantId,
        Uuid $invoiceId,
        string $serviceDescription,
        DateTimeImmutable $competenceDate,
        Money $totalAmount,
        float $issRate,
        Money $issAmount,
        string $idempotencyKey,
    ): self {
        $nfse = new self(
            $id,
            $tenantId,
            $invoiceId,
            NFSeStatus::Draft,
            null,
            null,
            null,
            $serviceDescription,
            $competenceDate,
            $totalAmount,
            $issRate,
            $issAmount,
            null,
            null,
            null,
            null,
            null,
            null,
            $idempotencyKey,
            new DateTimeImmutable,
        );

        $nfse->domainEvents[] = new NFSeRequested(
            $id,
            $tenantId,
            $invoiceId,
            $totalAmount->amount(),
        );

        return $nfse;
    }

    public function markProcessing(string $providerRef): void
    {
        $this->transitionTo(NFSeStatus::Processing);
        $this->providerRef = $providerRef;
    }

    public function markAuthorized(
        string $nfseNumber,
        string $verificationCode,
        ?string $pdfUrl,
        ?string $xmlContent,
        array $providerResponse,
    ): void {
        $this->transitionTo(NFSeStatus::Authorized);
        $this->nfseNumber = $nfseNumber;
        $this->verificationCode = $verificationCode;
        $this->pdfUrl = $pdfUrl;
        $this->xmlContent = $xmlContent;
        $this->providerResponse = $providerResponse;
        $this->authorizedAt = new DateTimeImmutable;
        $this->errorMessage = null;

        $this->domainEvents[] = new NFSeAuthorized(
            $this->id,
            $this->tenantId,
            $this->invoiceId,
            $nfseNumber,
        );
    }

    public function markDenied(string $errorMessage, array $providerResponse): void
    {
        $this->transitionTo(NFSeStatus::Denied);
        $this->errorMessage = $errorMessage;
        $this->providerResponse = $providerResponse;

        $this->domainEvents[] = new NFSeDenied(
            $this->id,
            $this->tenantId,
            $this->invoiceId,
            $errorMessage,
        );
    }

    public function cancel(string $reason): void
    {
        $this->transitionTo(NFSeStatus::Cancelled);
        $this->cancelledAt = new DateTimeImmutable;
        $this->errorMessage = $reason;

        $this->domainEvents[] = new NFSeCancelled(
            $this->id,
            $this->tenantId,
            $this->invoiceId,
            $this->nfseNumber ?? '',
        );
    }

    public function resetForRetry(): void
    {
        if (! $this->status->canRetry()) {
            throw new DomainException(
                "Cannot retry NFSe in status '{$this->status->value}'",
                'NFSE_CANNOT_RETRY',
                ['nfse_id' => $this->id->value(), 'status' => $this->status->value],
            );
        }

        $this->transitionTo(NFSeStatus::Draft);
        $this->providerRef = null;
        $this->errorMessage = null;
        $this->providerResponse = null;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function invoiceId(): Uuid
    {
        return $this->invoiceId;
    }

    public function status(): NFSeStatus
    {
        return $this->status;
    }

    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    public function nfseNumber(): ?string
    {
        return $this->nfseNumber;
    }

    public function verificationCode(): ?string
    {
        return $this->verificationCode;
    }

    public function serviceDescription(): string
    {
        return $this->serviceDescription;
    }

    public function competenceDate(): DateTimeImmutable
    {
        return $this->competenceDate;
    }

    public function totalAmount(): Money
    {
        return $this->totalAmount;
    }

    public function issRate(): float
    {
        return $this->issRate;
    }

    public function issAmount(): Money
    {
        return $this->issAmount;
    }

    public function pdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    public function xmlContent(): ?string
    {
        return $this->xmlContent;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function providerResponse(): ?array
    {
        return $this->providerResponse;
    }

    public function authorizedAt(): ?DateTimeImmutable
    {
        return $this->authorizedAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function transitionTo(NFSeStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new DomainException(
                "Cannot transition NFSe from '{$this->status->value}' to '{$target->value}'",
                'INVALID_NFSE_TRANSITION',
                [
                    'nfse_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $target->value,
                    'allowed' => array_map(
                        fn (NFSeStatus $s) => $s->value,
                        $this->status->allowedTransitions(),
                    ),
                ],
            );
        }

        $this->status = $target;
    }
}
