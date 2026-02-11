<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ViolationModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'violations';

    protected $fillable = [
        'id',
        'unit_id',
        'tenant_user_id',
        'reservation_id',
        'rule_id',
        'type',
        'severity',
        'description',
        'status',
        'is_automatic',
        'created_by',
        'upheld_by',
        'upheld_at',
        'revoked_by',
        'revoked_at',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
            'upheld_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    /**
     * @return BelongsTo<ReservationModel, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ReservationModel::class, 'reservation_id');
    }

    /**
     * @return BelongsTo<CondominiumRuleModel, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CondominiumRuleModel::class, 'rule_id');
    }

    /**
     * @return HasOne<ViolationContestationModel, $this>
     */
    public function contestation(): HasOne
    {
        return $this->hasOne(ViolationContestationModel::class, 'violation_id');
    }
}
