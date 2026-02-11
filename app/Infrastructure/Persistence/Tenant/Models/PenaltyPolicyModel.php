<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PenaltyPolicyModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'penalty_policies';

    protected $fillable = [
        'id',
        'violation_type',
        'occurrence_threshold',
        'penalty_type',
        'block_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_threshold' => 'integer',
            'block_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
