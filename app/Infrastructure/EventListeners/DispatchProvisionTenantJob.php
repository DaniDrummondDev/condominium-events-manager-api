<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListeners;

use App\Infrastructure\Jobs\Tenant\ProvisionTenantJob;
use Domain\Tenant\Events\TenantCreated;

class DispatchProvisionTenantJob
{
    public function handle(TenantCreated $event): void
    {
        ProvisionTenantJob::dispatch($event->aggregateId()->value());
    }
}
