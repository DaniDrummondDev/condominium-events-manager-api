<?php

declare(strict_types=1);

namespace Domain\Governance\Enums;

enum ContestationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected], true);
    }
}
