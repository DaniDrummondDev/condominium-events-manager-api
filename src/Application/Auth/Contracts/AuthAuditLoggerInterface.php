<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

use Application\Auth\DTOs\AuthAuditEntry;

interface AuthAuditLoggerInterface
{
    public function log(AuthAuditEntry $entry): void;
}
