<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'invoices';

    protected $fillable = [
        'id',
        'tenant_id',
        'subscription_id',
        'invoice_number',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'due_date',
        'paid_at',
        'voided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InvoiceItemModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItemModel::class, 'invoice_id');
    }

    /**
     * @return HasMany<PaymentModel, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'invoice_id');
    }
}
