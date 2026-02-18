<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Infrastructure\Persistence\Platform\Models\PlanModel;
use App\Infrastructure\Persistence\Platform\Models\PlanPriceModel;
use App\Infrastructure\Persistence\Platform\Models\PlanVersionModel;
use DateTimeImmutable;
use Domain\Billing\Entities\Invoice;
use Domain\Billing\Entities\InvoiceItem;
use Domain\Billing\Entities\Payment;
use Domain\Billing\Entities\Plan;
use Domain\Billing\Entities\PlanPrice;
use Domain\Billing\Entities\PlanVersion;
use Domain\Billing\Entities\Subscription;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\InvoiceItemType;
use Domain\Billing\Enums\PlanStatus;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

trait CreatesBillingData
{
    protected function createPlan(string $name = 'Starter', string $slug = 'starter'): Plan
    {
        return Plan::create(Uuid::generate(), $name, $slug);
    }

    protected function createPlanVersion(
        ?Uuid $planId = null,
    ): PlanVersion {
        return new PlanVersion(
            id: Uuid::generate(),
            planId: $planId ?? Uuid::generate(),
            version: 1,
            status: PlanStatus::Active,
            createdAt: new DateTimeImmutable,
        );
    }

    protected function createPlanPrice(
        ?Uuid $planVersionId = null,
        BillingCycle $billingCycle = BillingCycle::Monthly,
        int $priceInCents = 9900,
        string $currency = 'BRL',
        int $trialDays = 0,
    ): PlanPrice {
        return new PlanPrice(
            id: Uuid::generate(),
            planVersionId: $planVersionId ?? Uuid::generate(),
            billingCycle: $billingCycle,
            price: new Money($priceInCents, $currency),
            trialDays: $trialDays,
        );
    }

    protected function createSubscription(
        ?Uuid $tenantId = null,
        ?Uuid $planVersionId = null,
        BillingCycle $billingCycle = BillingCycle::Monthly,
    ): Subscription {
        $now = new DateTimeImmutable;
        $period = new BillingPeriod($now, $now->modify('+1 month'));

        return Subscription::create(
            Uuid::generate(),
            $tenantId ?? Uuid::generate(),
            $planVersionId ?? Uuid::generate(),
            $billingCycle,
            $period,
        );
    }

    protected function createInvoice(
        ?Uuid $tenantId = null,
        ?Uuid $subscriptionId = null,
        int $totalInCents = 9900,
        string $currency = 'BRL',
    ): Invoice {
        $invoiceNumber = InvoiceNumber::generate(2026, 1);
        $now = new DateTimeImmutable;

        $invoice = Invoice::create(
            Uuid::generate(),
            $tenantId ?? Uuid::generate(),
            $subscriptionId ?? Uuid::generate(),
            $invoiceNumber,
            $currency,
            $now,
        );

        $item = InvoiceItem::create(
            Uuid::generate(),
            $invoice->id(),
            InvoiceItemType::Plan,
            'Subscription — Monthly',
            1,
            new Money($totalInCents, $currency),
        );

        $invoice->addItem($item);
        $invoice->calculateTotals();

        return $invoice;
    }

    protected function createPayment(
        ?Uuid $invoiceId = null,
        int $amountInCents = 9900,
        string $gateway = 'stripe',
    ): Payment {
        return Payment::create(
            Uuid::generate(),
            $invoiceId ?? Uuid::generate(),
            $gateway,
            new Money($amountInCents),
        );
    }

    protected function createPlanInDatabase(
        string $name = 'Starter',
        string $slug = 'starter',
        int $priceInCents = 9900,
    ): array {
        $plan = PlanModel::query()->create([
            'id' => Uuid::generate()->value(),
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);

        $version = PlanVersionModel::query()->create([
            'id' => Uuid::generate()->value(),
            'plan_id' => $plan->id,
            'version' => 1,
            'status' => 'active',
            'created_at' => now(),
        ]);

        $price = PlanPriceModel::query()->create([
            'id' => Uuid::generate()->value(),
            'plan_version_id' => $version->id,
            'billing_cycle' => 'monthly',
            'price' => $priceInCents / 100,
            'currency' => 'BRL',
            'trial_days' => 0,
        ]);

        return ['plan' => $plan, 'version' => $version, 'price' => $price];
    }
}
