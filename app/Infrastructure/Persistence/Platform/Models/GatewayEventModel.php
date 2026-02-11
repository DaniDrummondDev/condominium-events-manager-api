<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GatewayEventModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'gateway_events';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'gateway',
        'event_type',
        'payload',
        'processed',
        'processed_at',
        'idempotency_key',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
