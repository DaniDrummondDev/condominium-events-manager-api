<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIUsageLogModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'ai_usage_logs';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_user_id',
        'action',
        'model',
        'tokens_input',
        'tokens_output',
        'latency_ms',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'latency_ms' => 'integer',
            'metadata' => 'array',
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
}
