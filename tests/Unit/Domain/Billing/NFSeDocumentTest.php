<?php

declare(strict_types=1);

use Domain\Billing\Entities\NFSeDocument;
use Domain\Billing\Enums\NFSeStatus;
use Domain\Billing\Events\NFSeAuthorized;
use Domain\Billing\Events\NFSeCancelled;
use Domain\Billing\Events\NFSeDenied;
use Domain\Billing\Events\NFSeRequested;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

function createNFSeDocument(NFSeStatus $status = NFSeStatus::Draft): NFSeDocument
{
    if ($status === NFSeStatus::Draft) {
        return NFSeDocument::create(
            Uuid::generate(),
            Uuid::generate(),
            Uuid::generate(),
            'Assinatura de plataforma - Fatura INV-2026-0001',
            new DateTimeImmutable('2026-02-01'),
            new Money(15000, 'BRL'),
            5.00,
            new Money(750, 'BRL'),
            'nfse:test-invoice-id',
        );
    }

    return new NFSeDocument(
        id: Uuid::generate(),
        tenantId: Uuid::generate(),
        invoiceId: Uuid::generate(),
        status: $status,
        providerRef: $status !== NFSeStatus::Draft ? 'provider-ref-123' : null,
        nfseNumber: $status === NFSeStatus::Authorized ? 'NFSe-000001' : null,
        verificationCode: $status === NFSeStatus::Authorized ? 'VERIFY-123' : null,
        serviceDescription: 'Assinatura de plataforma',
        competenceDate: new DateTimeImmutable('2026-02-01'),
        totalAmount: new Money(15000, 'BRL'),
        issRate: 5.00,
        issAmount: new Money(750, 'BRL'),
        pdfUrl: $status === NFSeStatus::Authorized ? 'https://example.com/nfse.pdf' : null,
        xmlContent: null,
        providerResponse: null,
        authorizedAt: $status === NFSeStatus::Authorized ? new DateTimeImmutable : null,
        cancelledAt: null,
        errorMessage: $status === NFSeStatus::Denied ? 'CNPJ inválido' : null,
        idempotencyKey: 'nfse:test-'.bin2hex(random_bytes(4)),
    );
}

// --- Factory method ---

describe('create', function () {
    test('creates draft NFSe document', function () {
        $id = Uuid::generate();
        $tenantId = Uuid::generate();
        $invoiceId = Uuid::generate();
        $totalAmount = new Money(15000, 'BRL');
        $issAmount = new Money(750, 'BRL');

        $nfse = NFSeDocument::create(
            $id, $tenantId, $invoiceId,
            'Assinatura - Fatura INV-2026-0001',
            new DateTimeImmutable('2026-02-01'),
            $totalAmount, 5.00, $issAmount, 'nfse:inv-123',
        );

        expect($nfse->id())->toBe($id)
            ->and($nfse->tenantId())->toBe($tenantId)
            ->and($nfse->invoiceId())->toBe($invoiceId)
            ->and($nfse->status())->toBe(NFSeStatus::Draft)
            ->and($nfse->providerRef())->toBeNull()
            ->and($nfse->nfseNumber())->toBeNull()
            ->and($nfse->totalAmount()->amount())->toBe(15000)
            ->and($nfse->issRate())->toBe(5.00)
            ->and($nfse->issAmount()->amount())->toBe(750)
            ->and($nfse->idempotencyKey())->toBe('nfse:inv-123')
            ->and($nfse->pdfUrl())->toBeNull()
            ->and($nfse->authorizedAt())->toBeNull()
            ->and($nfse->cancelledAt())->toBeNull()
            ->and($nfse->errorMessage())->toBeNull();
    });

    test('emits NFSeRequested event on create', function () {
        $nfse = createNFSeDocument();
        $events = $nfse->pullDomainEvents();

        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(NFSeRequested::class)
            ->and($events[0]->eventName())->toBe('billing.nfse.requested');
    });
});

// --- State transitions ---

describe('markProcessing', function () {
    test('transitions Draft to Processing', function () {
        $nfse = createNFSeDocument();
        $nfse->pullDomainEvents(); // clear create events

        $nfse->markProcessing('provider-ref-abc');

        expect($nfse->status())->toBe(NFSeStatus::Processing)
            ->and($nfse->providerRef())->toBe('provider-ref-abc');
    });

    test('cannot process from Authorized', function () {
        $nfse = createNFSeDocument(NFSeStatus::Authorized);

        $nfse->markProcessing('ref');
    })->throws(DomainException::class);
});

describe('markAuthorized', function () {
    test('transitions Processing to Authorized', function () {
        $nfse = createNFSeDocument(NFSeStatus::Processing);

        $nfse->markAuthorized('NFSe-123', 'VERIFY-456', 'https://pdf.url', '<xml/>', ['status' => 'ok']);

        expect($nfse->status())->toBe(NFSeStatus::Authorized)
            ->and($nfse->nfseNumber())->toBe('NFSe-123')
            ->and($nfse->verificationCode())->toBe('VERIFY-456')
            ->and($nfse->pdfUrl())->toBe('https://pdf.url')
            ->and($nfse->xmlContent())->toBe('<xml/>')
            ->and($nfse->authorizedAt())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($nfse->errorMessage())->toBeNull();
    });

    test('emits NFSeAuthorized event', function () {
        $nfse = createNFSeDocument(NFSeStatus::Processing);

        $nfse->markAuthorized('NFSe-123', 'V-456', null, null, []);

        $events = $nfse->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(NFSeAuthorized::class)
            ->and($events[0]->eventName())->toBe('billing.nfse.authorized');
    });

    test('cannot authorize from Draft', function () {
        $nfse = createNFSeDocument();
        $nfse->pullDomainEvents();

        $nfse->markAuthorized('NFSe-123', 'V-456', null, null, []);
    })->throws(DomainException::class);
});

describe('markDenied', function () {
    test('transitions Processing to Denied', function () {
        $nfse = createNFSeDocument(NFSeStatus::Processing);

        $nfse->markDenied('CNPJ inválido', ['error' => 'invalid_cnpj']);

        expect($nfse->status())->toBe(NFSeStatus::Denied)
            ->and($nfse->errorMessage())->toBe('CNPJ inválido')
            ->and($nfse->providerResponse())->toBe(['error' => 'invalid_cnpj']);
    });

    test('emits NFSeDenied event', function () {
        $nfse = createNFSeDocument(NFSeStatus::Processing);

        $nfse->markDenied('Error message', []);

        $events = $nfse->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(NFSeDenied::class)
            ->and($events[0]->eventName())->toBe('billing.nfse.denied');
    });

    test('cannot deny from Draft', function () {
        $nfse = createNFSeDocument();
        $nfse->pullDomainEvents();

        $nfse->markDenied('Error', []);
    })->throws(DomainException::class);
});

describe('cancel', function () {
    test('transitions Authorized to Cancelled', function () {
        $nfse = createNFSeDocument(NFSeStatus::Authorized);

        $nfse->cancel('Client request');

        expect($nfse->status())->toBe(NFSeStatus::Cancelled)
            ->and($nfse->cancelledAt())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($nfse->errorMessage())->toBe('Client request');
    });

    test('emits NFSeCancelled event', function () {
        $nfse = createNFSeDocument(NFSeStatus::Authorized);

        $nfse->cancel('Client request');

        $events = $nfse->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(NFSeCancelled::class)
            ->and($events[0]->eventName())->toBe('billing.nfse.cancelled');
    });

    test('cannot cancel from Draft', function () {
        $nfse = createNFSeDocument();
        $nfse->pullDomainEvents();

        $nfse->cancel('Reason');
    })->throws(DomainException::class);

    test('cannot cancel from Processing', function () {
        $nfse = createNFSeDocument(NFSeStatus::Processing);

        $nfse->cancel('Reason');
    })->throws(DomainException::class);
});

describe('resetForRetry', function () {
    test('resets Denied NFSe to Draft for retry', function () {
        $nfse = createNFSeDocument(NFSeStatus::Denied);

        $nfse->resetForRetry();

        expect($nfse->status())->toBe(NFSeStatus::Draft)
            ->and($nfse->providerRef())->toBeNull()
            ->and($nfse->errorMessage())->toBeNull()
            ->and($nfse->providerResponse())->toBeNull();
    });

    test('cannot retry from Draft', function () {
        $nfse = createNFSeDocument();
        $nfse->pullDomainEvents();

        $nfse->resetForRetry();
    })->throws(DomainException::class);

    test('cannot retry from Authorized', function () {
        $nfse = createNFSeDocument(NFSeStatus::Authorized);

        $nfse->resetForRetry();
    })->throws(DomainException::class);

    test('cannot retry from Cancelled', function () {
        $nfse = createNFSeDocument(NFSeStatus::Cancelled);

        $nfse->resetForRetry();
    })->throws(DomainException::class);
});

// --- pullDomainEvents ---

test('pullDomainEvents returns and clears events', function () {
    $nfse = createNFSeDocument();

    $events = $nfse->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAgain = $nfse->pullDomainEvents();
    expect($eventsAgain)->toBeEmpty();
});

// --- Full lifecycle ---

test('supports full lifecycle: Draft -> Processing -> Authorized -> Cancelled', function () {
    $nfse = createNFSeDocument();
    $nfse->pullDomainEvents();

    $nfse->markProcessing('ref-123');
    expect($nfse->status())->toBe(NFSeStatus::Processing);

    $nfse->markAuthorized('NFSe-001', 'V-001', 'https://pdf.url', '<xml/>', []);
    expect($nfse->status())->toBe(NFSeStatus::Authorized);

    $nfse->cancel('No longer needed');
    expect($nfse->status())->toBe(NFSeStatus::Cancelled);
});

test('supports retry lifecycle: Draft -> Processing -> Denied -> Draft -> Processing -> Authorized', function () {
    $nfse = createNFSeDocument();
    $nfse->pullDomainEvents();

    $nfse->markProcessing('ref-123');
    $nfse->markDenied('CNPJ error', ['error' => 'cnpj']);

    expect($nfse->status())->toBe(NFSeStatus::Denied);

    $nfse->resetForRetry();
    expect($nfse->status())->toBe(NFSeStatus::Draft);

    $nfse->markProcessing('ref-456');
    $nfse->markAuthorized('NFSe-002', 'V-002', null, null, []);

    expect($nfse->status())->toBe(NFSeStatus::Authorized)
        ->and($nfse->nfseNumber())->toBe('NFSe-002');
});

// --- Invalid transition error context ---

test('invalid transition throws with correct error code and context', function () {
    $nfse = createNFSeDocument();
    $nfse->pullDomainEvents();

    try {
        $nfse->markAuthorized('NFSe-123', 'V-456', null, null, []);
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_NFSE_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'draft')
            ->and($e->context())->toHaveKey('target_status', 'authorized')
            ->and($e->context())->toHaveKey('allowed');
    }
});
