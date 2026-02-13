<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessageModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'support_messages';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'support_request_id',
        'sender_id',
        'body',
        'is_internal',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SupportRequestModel, $this>
     */
    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequestModel::class, 'support_request_id');
    }

    /**
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'sender_id');
    }
}
