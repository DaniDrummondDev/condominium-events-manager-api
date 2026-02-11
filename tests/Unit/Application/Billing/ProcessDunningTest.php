<?php

declare(strict_types=1);

use Application\Billing\Contracts\DunningPolicyRepositoryInterface;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\UseCases\ProcessDunning;
use Domain\Billing\Entities\DunningPolicy;
use Domain\Billing\Entities\Invoice;
use Domain\Billing\Entities\Subscription;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\SubscriptionStatus;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\ValueObjects\Uuid;

test('processes dunning with default policy and suspends overdue subscriptions', function () {
    $tenantId = Uuid::generate();
    $subscriptionId = Uuid::generate();
    $invoiceId = Uuid::generate();

    // Create a past-due invoice with due date 40 days ago
    $dueDate = (new DateTimeImmutable)->modify('-40 days');

    $invoice = Invoice::create(
        $invoiceId,
        $tenantId,
        $subscriptionId,
        new InvoiceNumber(2025, 1),
        'BRL',
        $dueDate,
    );
    $invoice->issue();
    $invoice->markPastDue();

    // Create an active subscription
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    $subscription = Subscription::create(
        $subscriptionId,
        $tenantId,
        Uuid::generate(),
        BillingCycle::Monthly,
        $period,
    );

    // Dunning policy: suspend after 30 days
    $policy = new DunningPolicy(
        id: Uuid::generate(),
        name: 'Default Policy',
        maxRetries: 3,
        retryIntervals: [3, 5, 7],
        suspendAfterDays: 30,
        cancelAfterDays: 60,
        isDefault: true,
    );

    $dunningPolicyRepo = Mockery::mock(DunningPolicyRepositoryInterface::class);
    $dunningPolicyRepo->expects('findDefault')->andReturn($policy);

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
    $invoiceRepo->expects('findPastDue')->andReturn([$invoice]);

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $paymentRepo->expects('findByInvoiceId')->andReturn([]);

    $subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $subscriptionRepo->expects('findById')->andReturn($subscription);
    $subscriptionRepo->expects('save')->once();

    $useCase = new ProcessDunning(
        $invoiceRepo,
        $subscriptionRepo,
        $paymentRepo,
        $dunningPolicyRepo,
    );

    $result = $useCase->execute();

    expect($result)->toBeArray()
        ->and($result['processed'])->toBe(1)
        ->and($result['suspended'])->toBe(1);

    expect($subscription->status())->toBe(SubscriptionStatus::Suspended);
});

test('returns zero counts when no dunning policy exists', function () {
    $dunningPolicyRepo = Mockery::mock(DunningPolicyRepositoryInterface::class);
    $dunningPolicyRepo->expects('findDefault')->andReturnNull();

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
    $invoiceRepo->shouldNotReceive('findPastDue');

    $subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);

    $useCase = new ProcessDunning(
        $invoiceRepo,
        $subscriptionRepo,
        $paymentRepo,
        $dunningPolicyRepo,
    );

    $result = $useCase->execute();

    expect($result)->toBeArray()
        ->and($result['processed'])->toBe(0)
        ->and($result['suspended'])->toBe(0);
});
