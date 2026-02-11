<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

use Domain\Shared\Events\DomainEvent;

interface EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;

    /**
     * @param  array<DomainEvent>  $events
     */
    public function dispatchAll(array $events): void;
}
