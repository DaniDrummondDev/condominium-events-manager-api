<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Billing;

use App\Infrastructure\Persistence\Platform\Models\InvoiceModel;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPastDueInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct()
    {
        $this->queue = 'billing';
    }

    public function handle(): void
    {
        $now = new DateTimeImmutable;

        $count = InvoiceModel::query()
            ->where('status', 'open')
            ->where('due_date', '<', $now->format('Y-m-d'))
            ->update(['status' => 'past_due']);

        Log::info('ProcessPastDueInvoicesJob: marked invoices as past_due', [
            'count' => $count,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPastDueInvoicesJob: failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
