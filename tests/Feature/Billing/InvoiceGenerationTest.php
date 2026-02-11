<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();

    TenantModel::query()->create([
        'id' => $this->tenantId = \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'slug' => 'invoice-condo',
        'name' => 'Invoice Test Condo',
        'type' => 'vertical',
        'status' => 'active',
    ]);

    $data = $this->createPlanInDatabase('Pro', 'pro', 19900);
    $this->planVersionId = $data['version']->id;

    $createSub = app(\Application\Billing\UseCases\CreateSubscription::class);
    $sub = $createSub->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $this->planVersionId,
        billingCycle: 'monthly',
    ));
    $this->subscriptionId = $sub->id;
    $this->periodStart = $sub->currentPeriodStart;
    $this->periodEnd = $sub->currentPeriodEnd;
});

test('generates invoice for subscription', function () {
    $useCase = app(\Application\Billing\UseCases\GenerateInvoice::class);

    $result = $useCase->execute(new \Application\Billing\DTOs\GenerateInvoiceDTO(
        subscriptionId: $this->subscriptionId,
        periodStart: $this->periodStart,
        periodEnd: $this->periodEnd,
    ));

    expect($result->tenantId)->toBe($this->tenantId)
        ->and($result->subscriptionId)->toBe($this->subscriptionId)
        ->and($result->status)->toBe('open')
        ->and($result->totalInCents)->toBe(19900)
        ->and($result->invoiceNumber)->toStartWith('INV-2026-')
        ->and($result->items)->toHaveCount(1);
});

test('invoice generation is idempotent by period', function () {
    $useCase = app(\Application\Billing\UseCases\GenerateInvoice::class);
    $dto = new \Application\Billing\DTOs\GenerateInvoiceDTO(
        subscriptionId: $this->subscriptionId,
        periodStart: $this->periodStart,
        periodEnd: $this->periodEnd,
    );

    $first = $useCase->execute($dto);
    $second = $useCase->execute($dto);

    expect($first->id)->toBe($second->id);
});

test('sequential invoice numbering per tenant', function () {
    $useCase = app(\Application\Billing\UseCases\GenerateInvoice::class);

    $invoice1 = $useCase->execute(new \Application\Billing\DTOs\GenerateInvoiceDTO(
        subscriptionId: $this->subscriptionId,
        periodStart: $this->periodStart,
        periodEnd: $this->periodEnd,
    ));

    // Renew subscription and create new invoice
    $renewUseCase = app(\Application\Billing\UseCases\RenewSubscription::class);
    $renewed = $renewUseCase->execute($this->subscriptionId);

    $invoice2 = $useCase->execute(new \Application\Billing\DTOs\GenerateInvoiceDTO(
        subscriptionId: $this->subscriptionId,
        periodStart: $renewed->currentPeriodStart,
        periodEnd: $renewed->currentPeriodEnd,
    ));

    expect($invoice1->invoiceNumber)->toBe('INV-2026-0001')
        ->and($invoice2->invoiceNumber)->toBe('INV-2026-0002');
});
