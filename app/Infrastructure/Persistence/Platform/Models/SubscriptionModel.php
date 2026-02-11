<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SubscriptionModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'subscriptions';

    protected $fillable = [
        'id',
        'tenant_id',
        'plan_version_id',
        'status',
        'billing_cycle',
        'current_period_start',
        'current_period_end',
        'grace_period_end',
        'canceled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }
}
