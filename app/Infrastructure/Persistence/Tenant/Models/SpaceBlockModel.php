<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceBlockModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'space_blocks';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'space_id',
        'reason',
        'start_datetime',
        'end_datetime',
        'blocked_by',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SpaceModel, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(SpaceModel::class, 'space_id');
    }

    /**
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'blocked_by');
    }
}
