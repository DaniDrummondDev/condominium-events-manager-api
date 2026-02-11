<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

interface NotificationServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $channel, string $to, string $template, array $data): void;
}
