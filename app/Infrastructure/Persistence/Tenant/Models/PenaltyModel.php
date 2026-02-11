<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenaltyModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'penalties';

    protected $fillable = [
        'id',
        'violation_id',
        'unit_id',
        'type',
        'starts_at',
        'ends_at',
        'status',
        'revoked_at',
        'revoked_by',
        'revoked_reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ViolationModel, $this>
     */
    public function violation(): BelongsTo
    {
        return $this->belongsTo(ViolationModel::class, 'violation_id');
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
    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'revoked_by');
    }
}
