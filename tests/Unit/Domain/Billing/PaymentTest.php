<?php

declare(strict_types=1);

use Domain\Billing\Entities\Payment;
use Domain\Billing\Enums\PaymentStatus;
use Domain\Billing\Events\PaymentConfirmed;
use Domain\Billing\Events\PaymentFailed;
use Domain\Billing\Events\PaymentRefunded;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

function createPayment(PaymentStatus $status = PaymentStatus::Pending): Payment
{
    return new Payment(
        id: Uuid::generate(),
        invoiceId: Uuid::generate(),
        gateway: 'stripe',
        gatewayTransactionId: null,
        amount: new Money(10000, 'BRL'),
        status: $status,
        method: null,
        paidAt: null,
        failedAt: null,
        metadata: [],
        createdAt: new DateTimeImmutable,
    );
}

// --- Factory method ---

describe('create', function () {
    test('creates pending payment with correct attributes', function () {
        $id = Uuid::generate();
        $invoiceId = Uuid::generate();
        $amount = new Money(15000, 'BRL');
        $metadata = ['source' => 'checkout'];

        $payment = Payment::create($id, $invoiceId, 'stripe', $amount, $metadata);

        expect($payment->id())->toBe($id)
            ->and($payment->invoiceId())->toBe($invoiceId)
            ->and($payment->gateway())->toBe('stripe')
            ->and($payment->gatewayTransactionId())->toBeNull()
            ->and($payment->amount())->toBe($amount)
            ->and($payment->status())->toBe(PaymentStatus::Pending)
            ->and($payment->method())->toBeNull()
            ->and($payment->paidAt())->toBeNull()
            ->and($payment->failedAt())->toBeNull()
            ->and($payment->metadata())->toBe($metadata)
            ->and($payment->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
    });

    test('creates payment with empty metadata by default', function () {
        $payment = Payment::create(Uuid::generate(), Uuid::generate(), 'pagarme', new Money(5000, 'BRL'));

        expect($payment->metadata())->toBe([]);
    });
});

// --- authorize ---

describe('authorize', function () {
    test('authorizes pending payment and sets gateway transaction ID', function () {
        $payment = createPayment(PaymentStatus::Pending);

        $payment->authorize('txn_abc123');

        expect($payment->status())->toBe(PaymentStatus::Authorized)
            ->and($payment->gatewayTransactionId())->toBe('txn_abc123');
    });

    test('cannot authorize from Paid', function () {
        $payment = createPayment(PaymentStatus::Paid);

        $payment->authorize('txn_abc123');
    })->throws(DomainException::class);

    test('cannot authorize from Failed', function () {
        $payment = createPayment(PaymentStatus::Failed);

        $payment->authorize('txn_abc123');
    })->throws(DomainException::class);
});

// --- confirmPayment ---

describe('confirmPayment', function () {
    test('confirms payment from Pending', function () {
        $payment = createPayment(PaymentStatus::Pending);
        $paidAt = new DateTimeImmutable;

        $payment->confirmPayment($paidAt);

        expect($payment->status())->toBe(PaymentStatus::Paid)
            ->and($payment->paidAt())->toBe($paidAt);
    });

    test('confirms payment from Authorized', function () {
        $payment = createPayment(PaymentStatus::Authorized);
        $paidAt = new DateTimeImmutable;

        $payment->confirmPayment($paidAt);

        expect($payment->status())->toBe(PaymentStatus::Paid)
            ->and($payment->paidAt())->toBe($paidAt);
    });

    test('emits PaymentConfirmed event', function () {
        $payment = createPayment(PaymentStatus::Pending);

        $payment->confirmPayment(new DateTimeImmutable);

        $events = $payment->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(PaymentConfirmed::class);
    });

    test('cannot confirm from Failed', function () {
        $payment = createPayment(PaymentStatus::Failed);

        $payment->confirmPayment(new DateTimeImmutable);
    })->throws(DomainException::class);

    test('cannot confirm from Canceled', function () {
        $payment = createPayment(PaymentStatus::Canceled);

        $payment->confirmPayment(new DateTimeImmutable);
    })->throws(DomainException::class);
});

// --- fail ---

describe('fail', function () {
    test('fails pending payment', function () {
        $payment = createPayment(PaymentStatus::Pending);
        $failedAt = new DateTimeImmutable;

        $payment->fail($failedAt);

        expect($payment->status())->toBe(PaymentStatus::Failed)
            ->and($payment->failedAt())->toBe($failedAt);
    });

    test('fails authorized payment', function () {
        $payment = createPayment(PaymentStatus::Authorized);
        $failedAt = new DateTimeImmutable;

        $payment->fail($failedAt);

        expect($payment->status())->toBe(PaymentStatus::Failed)
            ->and($payment->failedAt())->toBe($failedAt);
    });

    test('emits PaymentFailed event', function () {
        $payment = createPayment(PaymentStatus::Pending);

        $payment->fail(new DateTimeImmutable);

        $events = $payment->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(PaymentFailed::class);
    });

    test('cannot fail from Paid', function () {
        $payment = createPayment(PaymentStatus::Paid);

        $payment->fail(new DateTimeImmutable);
    })->throws(DomainException::class);
});

// --- cancel ---

describe('cancel', function () {
    test('cancels pending payment', function () {
        $payment = createPayment(PaymentStatus::Pending);

        $payment->cancel();

        expect($payment->status())->toBe(PaymentStatus::Canceled);
    });

    test('cancels authorized payment', function () {
        $payment = createPayment(PaymentStatus::Authorized);

        $payment->cancel();

        expect($payment->status())->toBe(PaymentStatus::Canceled);
    });

    test('cannot cancel from Paid', function () {
        $payment = createPayment(PaymentStatus::Paid);

        $payment->cancel();
    })->throws(DomainException::class);

    test('cannot cancel from Failed', function () {
        $payment = createPayment(PaymentStatus::Failed);

        $payment->cancel();
    })->throws(DomainException::class);
});

// --- refund ---

describe('refund', function () {
    test('refunds paid payment', function () {
        $payment = createPayment(PaymentStatus::Paid);

        $payment->refund();

        expect($payment->status())->toBe(PaymentStatus::Refunded);
    });

    test('emits PaymentRefunded event', function () {
        $payment = createPayment(PaymentStatus::Paid);

        $payment->refund();

        $events = $payment->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(PaymentRefunded::class);
    });

    test('cannot refund from Pending', function () {
        $payment = createPayment(PaymentStatus::Pending);

        $payment->refund();
    })->throws(DomainException::class);

    test('cannot refund from Authorized', function () {
        $payment = createPayment(PaymentStatus::Authorized);

        $payment->refund();
    })->throws(DomainException::class);

    test('cannot refund from Refunded', function () {
        $payment = createPayment(PaymentStatus::Refunded);

        $payment->refund();
    })->throws(DomainException::class);
});

// --- Invalid transition error context ---

test('invalid transition throws with correct error code and context', function () {
    $payment = createPayment(PaymentStatus::Paid);

    try {
        $payment->cancel();
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_PAYMENT_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'paid')
            ->and($e->context())->toHaveKey('target_status', 'canceled')
            ->and($e->context())->toHaveKey('allowed');
    }
});

// --- pullDomainEvents ---

test('pullDomainEvents returns and clears events', function () {
    $payment = createPayment(PaymentStatus::Pending);
    $payment->confirmPayment(new DateTimeImmutable);

    $events = $payment->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAgain = $payment->pullDomainEvents();
    expect($eventsAgain)->toBeEmpty();
});

// --- Full lifecycle ---

test('supports full lifecycle: Pending -> Authorized -> Paid -> Refunded', function () {
    $payment = createPayment(PaymentStatus::Pending);

    $payment->authorize('txn_123');
    expect($payment->status())->toBe(PaymentStatus::Authorized);

    $payment->confirmPayment(new DateTimeImmutable);
    expect($payment->status())->toBe(PaymentStatus::Paid);

    $payment->refund();
    expect($payment->status())->toBe(PaymentStatus::Refunded);
});

test('supports direct payment lifecycle: Pending -> Paid', function () {
    $payment = createPayment(PaymentStatus::Pending);

    $payment->confirmPayment(new DateTimeImmutable);
    expect($payment->status())->toBe(PaymentStatus::Paid);
});

test('supports failure lifecycle: Pending -> Failed', function () {
    $payment = createPayment(PaymentStatus::Pending);

    $payment->fail(new DateTimeImmutable);
    expect($payment->status())->toBe(PaymentStatus::Failed);
});

test('supports cancellation lifecycle: Pending -> Canceled', function () {
    $payment = createPayment(PaymentStatus::Pending);

    $payment->cancel();
    expect($payment->status())->toBe(PaymentStatus::Canceled);
});
