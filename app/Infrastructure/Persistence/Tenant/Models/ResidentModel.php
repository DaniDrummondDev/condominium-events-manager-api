<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'residents';

    protected $fillable = [
        'id',
        'unit_id',
        'tenant_user_id',
        'role_in_unit',
        'is_primary',
        'status',
        'moved_in_at',
        'moved_out_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'moved_in_at' => 'datetime',
            'moved_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UnitModel, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitModel::class, 'unit_id');
    }

    /**
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function tenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'tenant_user_id');
    }
}
