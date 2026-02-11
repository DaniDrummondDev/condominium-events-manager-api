<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Billing;

use App\Infrastructure\Persistence\Platform\Models\InvoiceModel;
use App\Infrastructure\Persistence\Platform\Models\SubscriptionModel;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SuspendOverdueTenantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    private int $suspendAfterDays = 15;

    public function __construct()
    {
        $this->queue = 'billing';
    }

    public function handle(): void
    {
        $threshold = (new DateTimeImmutable)
            ->modify("-{$this->suspendAfterDays} days")
            ->format('Y-m-d');

        $overdueInvoices = InvoiceModel::query()
            ->where('status', 'past_due')
            ->where('due_date', '<=', $threshold)
            ->get();

        $suspended = 0;

        foreach ($overdueInvoices as $invoice) {
            SubscriptionModel::query()
                ->where('id', $invoice->subscription_id)
                ->whereIn('status', ['active', 'past_due', 'grace_period'])
                ->update(['status' => 'suspended']);

            TenantModel::query()
                ->where('id', $invoice->tenant_id)
                ->where('status', 'active')
                ->update(['status' => 'suspended']);

            $suspended++;
        }

        Log::info('SuspendOverdueTenantsJob: suspended overdue tenants', [
            'suspended' => $suspended,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SuspendOverdueTenantsJob: failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
