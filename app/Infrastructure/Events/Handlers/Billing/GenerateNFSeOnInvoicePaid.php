<?php

declare(strict_types=1);

namespace App\Infrastructure\Events\Handlers\Billing;

use App\Infrastructure\Jobs\Billing\GenerateNFSeJob;
use Domain\Billing\Events\InvoicePaid;
use Illuminate\Support\Facades\Log;

class GenerateNFSeOnInvoicePaid
{
    public function handle(InvoicePaid $event): void
    {
        if (! config('fiscal.auto_emit_on_payment', true)) {
            return;
        }

        $invoiceId = $event->aggregateId()->value();

        Log::info('GenerateNFSeOnInvoicePaid: dispatching NFSe generation', [
            'invoice_id' => $invoiceId,
        ]);

        GenerateNFSeJob::dispatch($invoiceId);
    }
}
