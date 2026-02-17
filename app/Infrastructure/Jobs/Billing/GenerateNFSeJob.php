<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Billing;

use Application\Billing\UseCases\GenerateNFSe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateNFSeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly string $invoiceId,
    ) {
        $this->queue = 'fiscal';
    }

    public function handle(GenerateNFSe $useCase): void
    {
        Log::info('GenerateNFSeJob: processing', [
            'invoice_id' => $this->invoiceId,
        ]);

        $nfse = $useCase->execute($this->invoiceId);

        Log::info('GenerateNFSeJob: completed', [
            'invoice_id' => $this->invoiceId,
            'nfse_id' => $nfse->id()->value(),
            'status' => $nfse->status()->value,
        ]);

        if ($nfse->status()->value === 'authorized') {
            SendNFSeByEmailJob::dispatch($nfse->id()->value());
        }
    }

    public function uniqueId(): string
    {
        return "nfse:{$this->invoiceId}";
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateNFSeJob: failed', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
