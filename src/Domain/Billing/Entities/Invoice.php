<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use DateTimeImmutable;
use Domain\Billing\Enums\InvoiceStatus;
use Domain\Billing\Events\InvoiceIssued;
use Domain\Billing\Events\InvoiceOverdue;
use Domain\Billing\Events\InvoicePaid;
use Domain\Billing\Events\InvoiceVoided;
use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class Invoice
{
    /** @var array<object> */
    private array $domainEvents = [];

    /** @var array<InvoiceItem> */
    private array $items = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $tenantId,
        private readonly Uuid $subscriptionId,
        private readonly InvoiceNumber $invoiceNumber,
        private InvoiceStatus $status,
        private readonly string $currency,
        private Money $subtotal,
        private Money $taxAmount,
        private Money $discountAmount,
        private Money $total,
        private readonly DateTimeImmutable $dueDate,
        private ?DateTimeImmutable $paidAt = null,
        private ?DateTimeImmutable $voidedAt = null,
        private readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $tenantId,
        Uuid $subscriptionId,
        InvoiceNumber $invoiceNumber,
        string $currency,
        DateTimeImmutable $dueDate,
    ): self {
        $zero = new Money(0, $currency);

        return new self(
            $id,
            $tenantId,
            $subscriptionId,
            $invoiceNumber,
            InvoiceStatus::Draft,
            $currency,
            $zero,
            $zero,
            $zero,
            $zero,
            $dueDate,
            createdAt: new DateTimeImmutable,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function subscriptionId(): Uuid
    {
        return $this->subscriptionId;
    }

    public function invoiceNumber(): InvoiceNumber
    {
        return $this->invoiceNumber;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function discountAmount(): Money
    {
        return $this->discountAmount;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function paidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function voidedAt(): ?DateTimeImmutable
    {
        return $this->voidedAt;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<InvoiceItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @param  array<InvoiceItem>  $items
     */
    public function loadItems(array $items): void
    {
        $this->items = $items;
    }

    public function addItem(InvoiceItem $item): void
    {
        if ($this->status !== InvoiceStatus::Draft) {
            throw new DomainException(
                'Cannot add items to a non-draft invoice',
                'INVOICE_NOT_DRAFT',
                ['invoice_id' => $this->id->value(), 'status' => $this->status->value],
            );
        }

        $this->items[] = $item;
    }

    public function calculateTotals(): void
    {
        if ($this->status !== InvoiceStatus::Draft) {
            throw new DomainException(
                'Cannot recalculate totals on a non-draft invoice',
                'INVOICE_NOT_DRAFT',
                ['invoice_id' => $this->id->value(), 'status' => $this->status->value],
            );
        }

        $subtotal = new Money(0, $this->currency);

        foreach ($this->items as $item) {
            $subtotal = $subtotal->add($item->total());
        }

        $this->subtotal = $subtotal;
        $this->total = $subtotal->add($this->taxAmount)->subtract($this->discountAmount);
    }

    public function issue(): void
    {
        $this->transitionTo(InvoiceStatus::Open);

        $this->domainEvents[] = new InvoiceIssued(
            $this->id,
            $this->tenantId,
            $this->total->amount(),
            $this->dueDate,
        );
    }

    public function markPaid(DateTimeImmutable $paidAt): void
    {
        $this->transitionTo(InvoiceStatus::Paid);
        $this->paidAt = $paidAt;

        $this->domainEvents[] = new InvoicePaid($this->id, $paidAt);
    }

    public function markPastDue(): void
    {
        $this->transitionTo(InvoiceStatus::PastDue);

        $this->domainEvents[] = new InvoiceOverdue($this->id, $this->dueDate);
    }

    public function void(): void
    {
        $this->transitionTo(InvoiceStatus::Void);
        $this->voidedAt = new DateTimeImmutable;

        $this->domainEvents[] = new InvoiceVoided($this->id);
    }

    public function markUncollectible(): void
    {
        $this->transitionTo(InvoiceStatus::Uncollectible);
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

    private function transitionTo(InvoiceStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new DomainException(
                "Cannot transition invoice from '{$this->status->value}' to '{$target->value}'",
                'INVALID_INVOICE_TRANSITION',
                [
                    'invoice_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $target->value,
                    'allowed' => array_map(
                        fn (InvoiceStatus $s) => $s->value,
                        $this->status->allowedTransitions(),
                    ),
                ],
            );
        }

        $this->status = $target;
    }
}
