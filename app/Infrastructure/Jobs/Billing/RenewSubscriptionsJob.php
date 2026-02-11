<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Billing;

use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\UseCases\GenerateInvoice;
use Application\Billing\UseCases\RenewSubscription;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenewSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct()
    {
        $this->queue = 'billing';
    }

    public function handle(
        SubscriptionRepositoryInterface $subscriptionRepository,
        RenewSubscription $renewSubscription,
        GenerateInvoice $generateInvoice,
    ): void {
        $now = new DateTimeImmutable;
        $dueSubscriptions = $subscriptionRepository->findDueForRenewal($now);
        $renewed = 0;

        foreach ($dueSubscriptions as $subscription) {
            try {
                $renewSubscription->execute($subscription->id()->value());

                $newPeriod = $subscription->currentPeriod();
                $generateInvoice->execute(
                    new \Application\Billing\DTOs\GenerateInvoiceDTO(
                        subscriptionId: $subscription->id()->value(),
                        periodStart: $newPeriod->start()->format('c'),
                        periodEnd: $newPeriod->end()->format('c'),
                    ),
                );

                $renewed++;
            } catch (\Throwable $e) {
                Log::error('RenewSubscriptionsJob: failed to renew subscription', [
                    'subscription_id' => $subscription->id()->value(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('RenewSubscriptionsJob: subscriptions renewed', [
            'renewed' => $renewed,
            'total' => count($dueSubscriptions),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RenewSubscriptionsJob: failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
