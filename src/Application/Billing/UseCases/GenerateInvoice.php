<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\InvoiceNumberGeneratorInterface;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\DTOs\GenerateInvoiceDTO;
use Application\Billing\DTOs\InvoiceDTO;
use Application\Billing\DTOs\InvoiceItemDTO;
use DateTimeImmutable;
use Domain\Billing\Entities\Invoice;
use Domain\Billing\Entities\InvoiceItem;
use Domain\Billing\Enums\InvoiceItemType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class GenerateInvoice
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PlanVersionRepositoryInterface $planVersionRepository,
        private InvoiceRepositoryInterface $invoiceRepository,
        private InvoiceNumberGeneratorInterface $invoiceNumberGenerator,
    ) {}

    public function execute(GenerateInvoiceDTO $dto): InvoiceDTO
    {
        $subscriptionId = Uuid::fromString($dto->subscriptionId);
        $periodStart = new DateTimeImmutable($dto->periodStart);
        $periodEnd = new DateTimeImmutable($dto->periodEnd);

        $existing = $this->invoiceRepository->findBySubscriptionAndPeriod(
            $subscriptionId,
            $periodStart,
            $periodEnd,
        );

        if ($existing !== null) {
            return $this->toDTO($existing);
        }

        $subscription = $this->subscriptionRepository->findById($subscriptionId);

        if ($subscription === null) {
            throw new DomainException(
                'Subscription not found',
                'SUBSCRIPTION_NOT_FOUND',
                ['subscription_id' => $dto->subscriptionId],
            );
        }

        $planVersion = $this->planVersionRepository->findById($subscription->planVersionId());

        if ($planVersion === null) {
            throw new DomainException(
                'Plan version not found',
                'PLAN_VERSION_NOT_FOUND',
                ['plan_version_id' => $subscription->planVersionId()->value()],
            );
        }

        $invoiceNumber = $this->invoiceNumberGenerator->generate($subscription->tenantId());
        $currency = $planVersion->price()->currency();

        $dueDate = $periodStart;

        $invoice = Invoice::create(
            Uuid::generate(),
            $subscription->tenantId(),
            $subscriptionId,
            $invoiceNumber,
            $currency,
            $dueDate,
        );

        $item = InvoiceItem::create(
            Uuid::generate(),
            $invoice->id(),
            InvoiceItemType::Plan,
            "Subscription — {$planVersion->billingCycle()->label()}",
            1,
            $planVersion->price(),
        );

        $invoice->addItem($item);
        $invoice->calculateTotals();
        $invoice->issue();

        $this->invoiceRepository->save($invoice);

        return $this->toDTO($invoice);
    }

    private function toDTO(Invoice $invoice): InvoiceDTO
    {
        $items = array_map(
            fn (InvoiceItem $item) => new InvoiceItemDTO(
                id: $item->id()->value(),
                invoiceId: $item->invoiceId()->value(),
                type: $item->type()->value,
                description: $item->description(),
                quantity: $item->quantity(),
                unitPriceInCents: $item->unitPrice()->amount(),
                totalInCents: $item->total()->amount(),
            ),
            $invoice->items(),
        );

        return new InvoiceDTO(
            id: $invoice->id()->value(),
            tenantId: $invoice->tenantId()->value(),
            subscriptionId: $invoice->subscriptionId()->value(),
            invoiceNumber: $invoice->invoiceNumber()->value(),
            status: $invoice->status()->value,
            currency: $invoice->currency(),
            subtotalInCents: $invoice->subtotal()->amount(),
            taxAmountInCents: $invoice->taxAmount()->amount(),
            discountAmountInCents: $invoice->discountAmount()->amount(),
            totalInCents: $invoice->total()->amount(),
            dueDate: $invoice->dueDate()->format('Y-m-d'),
            items: $items,
            paidAt: $invoice->paidAt()?->format('c'),
            voidedAt: $invoice->voidedAt()?->format('c'),
            createdAt: $invoice->createdAt()?->format('c'),
        );
    }
}
