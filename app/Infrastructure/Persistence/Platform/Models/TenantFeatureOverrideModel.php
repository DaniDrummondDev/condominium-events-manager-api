<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantFeatureOverrideModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'tenant_feature_overrides';

    protected $fillable = [
        'id',
        'tenant_id',
        'feature_id',
        'value',
        'reason',
        'expires_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
