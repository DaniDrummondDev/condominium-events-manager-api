<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\ValueObjects\Uuid;

interface InvoiceNumberGeneratorInterface
{
    public function generate(Uuid $tenantId): InvoiceNumber;
}
