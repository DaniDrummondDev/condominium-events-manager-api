<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementReadModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'announcement_reads';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'announcement_id',
        'tenant_user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AnnouncementModel, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(AnnouncementModel::class, 'announcement_id');
    }

    /**
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'tenant_user_id');
    }
}
