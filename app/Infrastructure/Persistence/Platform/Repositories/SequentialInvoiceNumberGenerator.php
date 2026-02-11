<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\InvoiceModel;
use Application\Billing\Contracts\InvoiceNumberGeneratorInterface;
use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\ValueObjects\Uuid;

class SequentialInvoiceNumberGenerator implements InvoiceNumberGeneratorInterface
{
    public function generate(Uuid $tenantId): InvoiceNumber
    {
        $year = (int) date('Y');
        $prefix = sprintf('INV-%04d-', $year);

        $lastInvoice = InvoiceModel::query()
            ->where('tenant_id', $tenantId->value())
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->first();

        $nextSequence = 1;

        if ($lastInvoice !== null) {
            $lastNumber = InvoiceNumber::fromString($lastInvoice->invoice_number);
            $nextSequence = $lastNumber->sequence() + 1;
        }

        return InvoiceNumber::generate($year, $nextSequence);
    }
}
