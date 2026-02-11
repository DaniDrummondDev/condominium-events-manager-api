<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\DunningPolicyRepositoryInterface;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use DateTimeImmutable;
use Domain\Billing\Entities\Invoice;
use Domain\Billing\Enums\PaymentStatus;
use Domain\Billing\Enums\SubscriptionStatus;

final readonly class ProcessDunning
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private DunningPolicyRepositoryInterface $dunningPolicyRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $policy = $this->dunningPolicyRepository->findDefault();

        if ($policy === null) {
            return ['processed' => 0, 'suspended' => 0];
        }

        $pastDueInvoices = $this->invoiceRepository->findPastDue();
        $processed = 0;
        $suspended = 0;

        foreach ($pastDueInvoices as $invoice) {
            $daysPastDue = $this->calculateDaysPastDue($invoice);
            $failedAttempts = $this->countFailedPaymentAttempts($invoice);

            if ($policy->shouldSuspend($daysPastDue)) {
                $this->suspendSubscription($invoice);
                $suspended++;
            }

            $processed++;
        }

        return [
            'processed' => $processed,
            'suspended' => $suspended,
        ];
    }

    private function calculateDaysPastDue(Invoice $invoice): int
    {
        $now = new DateTimeImmutable;
        $diff = $invoice->dueDate()->diff($now);

        return (int) $diff->days;
    }

    private function countFailedPaymentAttempts(Invoice $invoice): int
    {
        $payments = $this->paymentRepository->findByInvoiceId($invoice->id());

        return count(array_filter(
            $payments,
            fn ($p) => $p->status() === PaymentStatus::Failed,
        ));
    }

    private function suspendSubscription(Invoice $invoice): void
    {
        $subscription = $this->subscriptionRepository->findById($invoice->subscriptionId());

        if ($subscription === null) {
            return;
        }

        $status = $subscription->status();

        // Follow state machine: Active → PastDue → GracePeriod → Suspended
        if ($status === SubscriptionStatus::Active) {
            $subscription->markPastDue();
            $status = $subscription->status();
        }

        if ($status === SubscriptionStatus::PastDue) {
            $subscription->enterGracePeriod(new DateTimeImmutable('+7 days'));
            $status = $subscription->status();
        }

        if ($status === SubscriptionStatus::GracePeriod) {
            $subscription->suspend('non_payment');
            $this->subscriptionRepository->save($subscription);
        }
    }
}
