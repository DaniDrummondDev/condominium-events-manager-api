<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Events\DomainEvent;
use Illuminate\Contracts\Events\Dispatcher;

class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {}

    public function dispatch(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }

    /**
     * @param  array<DomainEvent>  $events
     */
    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}
