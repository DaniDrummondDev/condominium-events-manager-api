<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViolationContestationModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'violation_contestations';

    protected $fillable = [
        'id',
        'violation_id',
        'tenant_user_id',
        'reason',
        'status',
        'response',
        'responded_by',
        'responded_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
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
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function tenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'tenant_user_id');
    }

    /**
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function respondedByUser(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'responded_by');
    }
}
