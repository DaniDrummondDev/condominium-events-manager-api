<?php

declare(strict_types=1);

use Application\Billing\Contracts\InvoiceNumberGeneratorInterface;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\DTOs\GenerateInvoiceDTO;
use Application\Billing\DTOs\InvoiceDTO;
use Application\Billing\UseCases\GenerateInvoice;
use Domain\Billing\Entities\Invoice;
use Domain\Billing\Entities\PlanVersion;
use Domain\Billing\Entities\Subscription;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\InvoiceStatus;
use Domain\Billing\Enums\PlanStatus;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

test('generates an invoice for a subscription successfully', function () {
    $tenantId = Uuid::generate();
    $subscriptionId = Uuid::generate();
    $planVersionId = Uuid::generate();

    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    $subscription = Subscription::create(
        $subscriptionId,
        $tenantId,
        $planVersionId,
        BillingCycle::Monthly,
        $period,
    );

    $planVersion = new PlanVersion(
        id: $planVersionId,
        planId: Uuid::generate(),
        version: 1,
        price: new Money(9900, 'BRL'),
        billingCycle: BillingCycle::Monthly,
        trialDays: 0,
        status: PlanStatus::Active,
        createdAt: new DateTimeImmutable,
    );

    $invoiceNumber = new InvoiceNumber(2025, 1);

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
    $invoiceRepo->expects('findBySubscriptionAndPeriod')->andReturnNull();
    $invoiceRepo->expects('save')->once();

    $subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $subscriptionRepo->expects('findById')->andReturn($subscription);

    $planVersionRepo = Mockery::mock(PlanVersionRepositoryInterface::class);
    $planVersionRepo->expects('findById')->andReturn($planVersion);

    $numberGenerator = Mockery::mock(InvoiceNumberGeneratorInterface::class);
    $numberGenerator->expects('generate')->andReturn($invoiceNumber);

    $useCase = new GenerateInvoice(
        $subscriptionRepo,
        $planVersionRepo,
        $invoiceRepo,
        $numberGenerator,
    );

    $dto = new GenerateInvoiceDTO(
        subscriptionId: $subscriptionId->value(),
        periodStart: '2025-01-01',
        periodEnd: '2025-02-01',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(InvoiceDTO::class)
        ->and($result->tenantId)->toBe($tenantId->value())
        ->and($result->subscriptionId)->toBe($subscriptionId->value())
        ->and($result->invoiceNumber)->toBe('INV-2025-0001')
        ->and($result->status)->toBe(InvoiceStatus::Open->value)
        ->and($result->currency)->toBe('BRL')
        ->and($result->totalInCents)->toBe(9900);
});

test('returns existing invoice for same period (idempotent)', function () {
    $tenantId = Uuid::generate();
    $subscriptionId = Uuid::generate();

    $invoiceNumber = new InvoiceNumber(2025, 1);
    $dueDate = new DateTimeImmutable('2025-01-01');

    $existingInvoice = Invoice::create(
        Uuid::generate(),
        $tenantId,
        $subscriptionId,
        $invoiceNumber,
        'BRL',
        $dueDate,
    );

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
    $invoiceRepo->expects('findBySubscriptionAndPeriod')->andReturn($existingInvoice);
    $invoiceRepo->shouldNotReceive('save');

    $subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $subscriptionRepo->shouldNotReceive('findById');

    $planVersionRepo = Mockery::mock(PlanVersionRepositoryInterface::class);
    $planVersionRepo->shouldNotReceive('findById');

    $numberGenerator = Mockery::mock(InvoiceNumberGeneratorInterface::class);
    $numberGenerator->shouldNotReceive('generate');

    $useCase = new GenerateInvoice(
        $subscriptionRepo,
        $planVersionRepo,
        $invoiceRepo,
        $numberGenerator,
    );

    $dto = new GenerateInvoiceDTO(
        subscriptionId: $subscriptionId->value(),
        periodStart: '2025-01-01',
        periodEnd: '2025-02-01',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(InvoiceDTO::class)
        ->and($result->subscriptionId)->toBe($subscriptionId->value())
        ->and($result->invoiceNumber)->toBe('INV-2025-0001');
});

test('throws when subscription is not found', function () {
    $subscriptionId = Uuid::generate();

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
    $invoiceRepo->expects('findBySubscriptionAndPeriod')->andReturnNull();

    $subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $subscriptionRepo->expects('findById')->andReturnNull();

    $planVersionRepo = Mockery::mock(PlanVersionRepositoryInterface::class);
    $numberGenerator = Mockery::mock(InvoiceNumberGeneratorInterface::class);

    $useCase = new GenerateInvoice(
        $subscriptionRepo,
        $planVersionRepo,
        $invoiceRepo,
        $numberGenerator,
    );

    $dto = new GenerateInvoiceDTO(
        subscriptionId: $subscriptionId->value(),
        periodStart: '2025-01-01',
        periodEnd: '2025-02-01',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SUBSCRIPTION_NOT_FOUND')
            ->and($e->context())->toHaveKey('subscription_id', $subscriptionId->value());
    }
});
