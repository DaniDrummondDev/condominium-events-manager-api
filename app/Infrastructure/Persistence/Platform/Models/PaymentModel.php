<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'payments';

    protected $fillable = [
        'id',
        'invoice_id',
        'gateway',
        'gateway_transaction_id',
        'amount',
        'currency',
        'status',
        'method',
        'paid_at',
        'failed_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
