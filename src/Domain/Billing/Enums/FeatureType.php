<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum FeatureType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Enum = 'enum';
}
