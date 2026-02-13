<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIActionLogModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'ai_action_logs';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_user_id',
        'tool_name',
        'input_data',
        'output_data',
        'status',
        'confirmed_by',
        'executed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'output_data' => 'array',
            'executed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
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
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'confirmed_by');
    }
}
