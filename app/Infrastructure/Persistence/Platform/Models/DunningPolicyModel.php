<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DunningPolicyModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'dunning_policies';

    protected $fillable = [
        'id',
        'name',
        'max_retries',
        'retry_intervals',
        'suspend_after_days',
        'cancel_after_days',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retry_intervals' => 'array',
            'is_default' => 'boolean',
            'max_retries' => 'integer',
            'suspend_after_days' => 'integer',
            'cancel_after_days' => 'integer',
        ];
    }
}
