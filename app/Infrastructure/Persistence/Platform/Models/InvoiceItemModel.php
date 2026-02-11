<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InvoiceItemModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'invoice_items';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'invoice_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }
}
