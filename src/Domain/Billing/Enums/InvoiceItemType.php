<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum InvoiceItemType: string
{
    case Plan = 'plan';
    case Addon = 'addon';
    case Adjustment = 'adjustment';
    case Credit = 'credit';
    case Proration = 'proration';
}
