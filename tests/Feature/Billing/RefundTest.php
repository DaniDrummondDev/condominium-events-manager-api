<?php

declare(strict_types=1);

use App\Infrastructure\Gateways\Payment\FakePaymentGateway;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Domain\Shared\Exceptions\DomainException;
use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();

    $this->fakeGateway = new FakePaymentGateway;
    app()->instance(PaymentGatewayInterface::class, $this->fakeGateway);

    TenantModel::query()->create([
        'id' => $this->tenantId = \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'slug' => 'refund-condo',
        'name' => 'Refund Test Condo',
        'type' => 'vertical',
        'status' => 'active',
    ]);

    $data = $this->createPlanInDatabase('Starter', 'starter-refund', 9900);

    $createSub = app(\Application\Billing\UseCases\CreateSubscription::class);
    $sub = $createSub->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $data['version']->id,
        billingCycle: 'monthly',
    ));

    $genInvoice = app(\Application\Billing\UseCases\GenerateInvoice::class);
    $invoice = $genInvoice->execute(new \Application\Billing\DTOs\GenerateInvoiceDTO(
        subscriptionId: $sub->id,
        periodStart: $sub->currentPeriodStart,
        periodEnd: $sub->currentPeriodEnd,
    ));

    $processPayment = app(\Application\Billing\UseCases\ProcessPayment::class);
    $this->payment = $processPayment->execute($invoice->id, 'tok_test_refund');
});

test('issues refund for confirmed payment', function () {
    $useCase = app(\Application\Billing\UseCases\IssueRefund::class);

    $payment = $useCase->execute(
        $this->payment->id()->value(),
        9900,
        'Customer requested full refund',
    );

    expect($payment->status()->value)->toBe('refunded');
});

test('refund amount cannot exceed payment amount', function () {
    $useCase = app(\Application\Billing\UseCases\IssueRefund::class);

    $useCase->execute(
        $this->payment->id()->value(),
        99999,
        'Trying to refund more than paid',
    );
})->throws(DomainException::class, 'Refund amount exceeds payment amount');

test('refund gateway records refund', function () {
    $useCase = app(\Application\Billing\UseCases\IssueRefund::class);

    $useCase->execute(
        $this->payment->id()->value(),
        5000,
        'Partial refund for service issue',
    );

    $refunds = $this->fakeGateway->getRefunds();

    expect($refunds)->toHaveCount(1)
        ->and($refunds[0]->amountInCents)->toBe(5000)
        ->and($refunds[0]->reason)->toBe('Partial refund for service issue');
});
