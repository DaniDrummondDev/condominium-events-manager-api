<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportRequestModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'support_requests';

    protected $fillable = [
        'id',
        'tenant_user_id',
        'subject',
        'category',
        'status',
        'priority',
        'closed_at',
        'closed_reason',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'tenant_user_id');
    }

    /**
     * @return HasMany<SupportMessageModel, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessageModel::class, 'support_request_id');
    }
}
