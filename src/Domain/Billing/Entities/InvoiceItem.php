<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use Domain\Billing\Enums\InvoiceItemType;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class InvoiceItem
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $invoiceId,
        private readonly InvoiceItemType $type,
        private readonly string $description,
        private readonly int $quantity,
        private readonly Money $unitPrice,
        private readonly Money $total,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $invoiceId,
        InvoiceItemType $type,
        string $description,
        int $quantity,
        Money $unitPrice,
    ): self {
        return new self(
            $id,
            $invoiceId,
            $type,
            $description,
            $quantity,
            $unitPrice,
            $unitPrice->multiply($quantity),
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

    public function type(): InvoiceItemType
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function total(): Money
    {
        return $this->total;
    }
}
