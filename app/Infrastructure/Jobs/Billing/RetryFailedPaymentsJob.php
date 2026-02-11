<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Billing;

use Application\Billing\UseCases\ProcessDunning;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct()
    {
        $this->queue = 'billing';
    }

    public function handle(ProcessDunning $processDunning): void
    {
        $result = $processDunning->execute();

        Log::info('RetryFailedPaymentsJob: dunning processed', $result);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RetryFailedPaymentsJob: failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
