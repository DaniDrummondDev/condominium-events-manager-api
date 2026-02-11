<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformAuditLogModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'platform_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'context',
        'ip_address',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
