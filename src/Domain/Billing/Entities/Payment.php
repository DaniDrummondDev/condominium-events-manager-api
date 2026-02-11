<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use DateTimeImmutable;
use Domain\Billing\Enums\PaymentStatus;
use Domain\Billing\Events\PaymentConfirmed;
use Domain\Billing\Events\PaymentFailed;
use Domain\Billing\Events\PaymentRefunded;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class Payment
{
    /** @var array<object> */
    private array $domainEvents = [];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $invoiceId,
        private readonly string $gateway,
        private ?string $gatewayTransactionId,
        private readonly Money $amount,
        private PaymentStatus $status,
        private ?string $method,
        private ?DateTimeImmutable $paidAt,
        private ?DateTimeImmutable $failedAt,
        private array $metadata,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        Uuid $id,
        Uuid $invoiceId,
        string $gateway,
        Money $amount,
        array $metadata = [],
    ): self {
        return new self(
            $id,
            $invoiceId,
            $gateway,
            null,
            $amount,
            PaymentStatus::Pending,
            null,
            null,
            null,
            $metadata,
            new DateTimeImmutable,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function invoiceId(): Uuid
    {
        return $this->invoiceId;
    }

    public function gateway(): string
    {
        return $this->gateway;
    }

    public function gatewayTransactionId(): ?string
    {
        return $this->gatewayTransactionId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function method(): ?string
    {
        return $this->method;
    }

    public function paidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function failedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function authorize(string $gatewayTransactionId): void
    {
        $this->transitionTo(PaymentStatus::Authorized);
        $this->gatewayTransactionId = $gatewayTransactionId;
    }

    public function confirmPayment(DateTimeImmutable $paidAt): void
    {
        $this->transitionTo(PaymentStatus::Paid);
        $this->paidAt = $paidAt;

        $this->domainEvents[] = new PaymentConfirmed(
            $this->id,
            $this->invoiceId,
            $this->amount->amount(),
        );
    }

    public function fail(DateTimeImmutable $failedAt): void
    {
        $this->transitionTo(PaymentStatus::Failed);
        $this->failedAt = $failedAt;

        $this->domainEvents[] = new PaymentFailed(
            $this->id,
            $this->invoiceId,
            'Payment failed at gateway',
        );
    }

    public function cancel(): void
    {
        $this->transitionTo(PaymentStatus::Canceled);
    }

    public function refund(): void
    {
        $this->transitionTo(PaymentStatus::Refunded);

        $this->domainEvents[] = new PaymentRefunded(
            $this->id,
            $this->amount->amount(),
        );
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

    private function transitionTo(PaymentStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new DomainException(
                "Cannot transition payment from '{$this->status->value}' to '{$target->value}'",
                'INVALID_PAYMENT_TRANSITION',
                [
                    'payment_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $target->value,
                    'allowed' => array_map(
                        fn (PaymentStatus $s) => $s->value,
                        $this->status->allowedTransitions(),
                    ),
                ],
            );
        }

        $this->status = $target;
    }
}
