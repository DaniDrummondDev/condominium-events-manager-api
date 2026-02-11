<?php

declare(strict_types=1);

use Domain\Billing\Entities\Invoice;
use Domain\Billing\Entities\InvoiceItem;
use Domain\Billing\Enums\InvoiceItemType;
use Domain\Billing\Enums\InvoiceStatus;
use Domain\Billing\Events\InvoiceIssued;
use Domain\Billing\Events\InvoiceOverdue;
use Domain\Billing\Events\InvoicePaid;
use Domain\Billing\Events\InvoiceVoided;
use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

function createInvoice(InvoiceStatus $status = InvoiceStatus::Draft): Invoice
{
    $zero = new Money(0, 'BRL');

    return new Invoice(
        id: Uuid::generate(),
        tenantId: Uuid::generate(),
        subscriptionId: Uuid::generate(),
        invoiceNumber: InvoiceNumber::generate(2025, 1),
        status: $status,
        currency: 'BRL',
        subtotal: $zero,
        taxAmount: $zero,
        discountAmount: $zero,
        total: $zero,
        dueDate: new DateTimeImmutable('2025-02-01'),
    );
}

function createInvoiceItem(Uuid $invoiceId, int $quantity = 1, int $unitPriceCents = 10000): InvoiceItem
{
    return InvoiceItem::create(
        Uuid::generate(),
        $invoiceId,
        InvoiceItemType::Plan,
        'Plano Mensal',
        $quantity,
        new Money($unitPriceCents, 'BRL'),
    );
}

// --- Factory method ---

describe('create', function () {
    test('creates draft invoice with zero totals', function () {
        $id = Uuid::generate();
        $tenantId = Uuid::generate();
        $subscriptionId = Uuid::generate();
        $invoiceNumber = InvoiceNumber::generate(2025, 42);
        $dueDate = new DateTimeImmutable('2025-03-01');

        $invoice = Invoice::create($id, $tenantId, $subscriptionId, $invoiceNumber, 'BRL', $dueDate);

        expect($invoice->id())->toBe($id)
            ->and($invoice->tenantId())->toBe($tenantId)
            ->and($invoice->subscriptionId())->toBe($subscriptionId)
            ->and($invoice->invoiceNumber())->toBe($invoiceNumber)
            ->and($invoice->status())->toBe(InvoiceStatus::Draft)
            ->and($invoice->currency())->toBe('BRL')
            ->and($invoice->subtotal()->amount())->toBe(0)
            ->and($invoice->taxAmount()->amount())->toBe(0)
            ->and($invoice->discountAmount()->amount())->toBe(0)
            ->and($invoice->total()->amount())->toBe(0)
            ->and($invoice->dueDate())->toBe($dueDate)
            ->and($invoice->paidAt())->toBeNull()
            ->and($invoice->voidedAt())->toBeNull()
            ->and($invoice->createdAt())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($invoice->items())->toBeEmpty();
    });
});

// --- addItem ---

describe('addItem', function () {
    test('adds item to draft invoice', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);
        $item = createInvoiceItem($invoice->id());

        $invoice->addItem($item);

        expect($invoice->items())->toHaveCount(1)
            ->and($invoice->items()[0])->toBe($item);
    });

    test('adds multiple items to draft invoice', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);
        $item1 = createInvoiceItem($invoice->id());
        $item2 = createInvoiceItem($invoice->id(), 2, 5000);

        $invoice->addItem($item1);
        $invoice->addItem($item2);

        expect($invoice->items())->toHaveCount(2);
    });

    test('cannot add item to non-draft invoice', function () {
        $invoice = createInvoice(InvoiceStatus::Open);
        $item = createInvoiceItem($invoice->id());

        $invoice->addItem($item);
    })->throws(DomainException::class, 'Cannot add items to a non-draft invoice');
});

// --- calculateTotals ---

describe('calculateTotals', function () {
    test('calculates subtotal from items', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);
        $item1 = createInvoiceItem($invoice->id(), 1, 10000); // 100.00
        $item2 = createInvoiceItem($invoice->id(), 2, 5000);  // 2 x 50.00 = 100.00

        $invoice->addItem($item1);
        $invoice->addItem($item2);
        $invoice->calculateTotals();

        expect($invoice->subtotal()->amount())->toBe(20000)
            ->and($invoice->total()->amount())->toBe(20000);
    });

    test('calculates total with zero items', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);
        $invoice->calculateTotals();

        expect($invoice->subtotal()->amount())->toBe(0)
            ->and($invoice->total()->amount())->toBe(0);
    });

    test('cannot calculate totals on non-draft invoice', function () {
        $invoice = createInvoice(InvoiceStatus::Open);

        $invoice->calculateTotals();
    })->throws(DomainException::class, 'Cannot recalculate totals on a non-draft invoice');
});

// --- loadItems ---

describe('loadItems', function () {
    test('loads items into invoice', function () {
        $invoice = createInvoice();
        $items = [
            createInvoiceItem($invoice->id(), 1, 10000),
            createInvoiceItem($invoice->id(), 3, 2000),
        ];

        $invoice->loadItems($items);

        expect($invoice->items())->toHaveCount(2);
    });
});

// --- State transitions ---

describe('issue', function () {
    test('issues draft invoice to Open status', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);

        $invoice->issue();

        expect($invoice->status())->toBe(InvoiceStatus::Open);
    });

    test('emits InvoiceIssued event', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);

        $invoice->issue();

        $events = $invoice->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(InvoiceIssued::class);
    });

    test('cannot issue from Open', function () {
        $invoice = createInvoice(InvoiceStatus::Open);

        $invoice->issue();
    })->throws(DomainException::class);

    test('cannot issue from Paid', function () {
        $invoice = createInvoice(InvoiceStatus::Paid);

        $invoice->issue();
    })->throws(DomainException::class);
});

describe('markPaid', function () {
    test('marks Open invoice as Paid', function () {
        $invoice = createInvoice(InvoiceStatus::Open);
        $paidAt = new DateTimeImmutable;

        $invoice->markPaid($paidAt);

        expect($invoice->status())->toBe(InvoiceStatus::Paid)
            ->and($invoice->paidAt())->toBe($paidAt);
    });

    test('marks PastDue invoice as Paid', function () {
        $invoice = createInvoice(InvoiceStatus::PastDue);
        $paidAt = new DateTimeImmutable;

        $invoice->markPaid($paidAt);

        expect($invoice->status())->toBe(InvoiceStatus::Paid)
            ->and($invoice->paidAt())->toBe($paidAt);
    });

    test('emits InvoicePaid event', function () {
        $invoice = createInvoice(InvoiceStatus::Open);

        $invoice->markPaid(new DateTimeImmutable);

        $events = $invoice->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(InvoicePaid::class);
    });

    test('cannot mark Draft as Paid', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);

        $invoice->markPaid(new DateTimeImmutable);
    })->throws(DomainException::class);
});

describe('markPastDue', function () {
    test('marks Open invoice as PastDue', function () {
        $invoice = createInvoice(InvoiceStatus::Open);

        $invoice->markPastDue();

        expect($invoice->status())->toBe(InvoiceStatus::PastDue);
    });

    test('emits InvoiceOverdue event', function () {
        $invoice = createInvoice(InvoiceStatus::Open);

        $invoice->markPastDue();

        $events = $invoice->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(InvoiceOverdue::class);
    });

    test('cannot mark Draft as PastDue', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);

        $invoice->markPastDue();
    })->throws(DomainException::class);
});

describe('void', function () {
    test('voids Open invoice', function () {
        $invoice = createInvoice(InvoiceStatus::Open);

        $invoice->void();

        expect($invoice->status())->toBe(InvoiceStatus::Void)
            ->and($invoice->voidedAt())->toBeInstanceOf(DateTimeImmutable::class);
    });

    test('emits InvoiceVoided event', function () {
        $invoice = createInvoice(InvoiceStatus::Open);

        $invoice->void();

        $events = $invoice->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(InvoiceVoided::class);
    });

    test('cannot void Draft invoice', function () {
        $invoice = createInvoice(InvoiceStatus::Draft);

        $invoice->void();
    })->throws(DomainException::class);

    test('cannot void Paid invoice', function () {
        $invoice = createInvoice(InvoiceStatus::Paid);

        $invoice->void();
    })->throws(DomainException::class);
});

describe('markUncollectible', function () {
    test('marks PastDue invoice as Uncollectible', function () {
        $invoice = createInvoice(InvoiceStatus::PastDue);

        $invoice->markUncollectible();

        expect($invoice->status())->toBe(InvoiceStatus::Uncollectible);
    });

    test('cannot mark Open invoice as Uncollectible', function () {
        $invoice = createInvoice(InvoiceStatus::Open);

        $invoice->markUncollectible();
    })->throws(DomainException::class);
});

// --- Invalid transition error context ---

test('invalid transition throws with correct error code and context', function () {
    $invoice = createInvoice(InvoiceStatus::Draft);

    try {
        $invoice->markPaid(new DateTimeImmutable);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_INVOICE_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'draft')
            ->and($e->context())->toHaveKey('target_status', 'paid')
            ->and($e->context())->toHaveKey('allowed');
    }
});

// --- pullDomainEvents ---

test('pullDomainEvents returns and clears events', function () {
    $invoice = createInvoice(InvoiceStatus::Draft);
    $invoice->issue();

    $events = $invoice->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAgain = $invoice->pullDomainEvents();
    expect($eventsAgain)->toBeEmpty();
});

// --- Full lifecycle ---

test('supports full lifecycle: Draft -> Open -> PastDue -> Paid', function () {
    $invoice = createInvoice(InvoiceStatus::Draft);

    $invoice->issue();
    expect($invoice->status())->toBe(InvoiceStatus::Open);

    $invoice->markPastDue();
    expect($invoice->status())->toBe(InvoiceStatus::PastDue);

    $invoice->markPaid(new DateTimeImmutable);
    expect($invoice->status())->toBe(InvoiceStatus::Paid);
});

test('supports void lifecycle: Draft -> Open -> Void', function () {
    $invoice = createInvoice(InvoiceStatus::Draft);

    $invoice->issue();
    expect($invoice->status())->toBe(InvoiceStatus::Open);

    $invoice->void();
    expect($invoice->status())->toBe(InvoiceStatus::Void);
});

test('supports uncollectible lifecycle: Draft -> Open -> PastDue -> Uncollectible', function () {
    $invoice = createInvoice(InvoiceStatus::Draft);

    $invoice->issue();
    expect($invoice->status())->toBe(InvoiceStatus::Open);

    $invoice->markPastDue();
    expect($invoice->status())->toBe(InvoiceStatus::PastDue);

    $invoice->markUncollectible();
    expect($invoice->status())->toBe(InvoiceStatus::Uncollectible);
});
