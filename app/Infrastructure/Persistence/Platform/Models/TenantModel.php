<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'tenants';

    protected $fillable = [
        'id',
        'slug',
        'name',
        'type',
        'status',
        'config',
        'database_name',
        'provisioned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'provisioned_at' => 'datetime',
        ];
    }
}
