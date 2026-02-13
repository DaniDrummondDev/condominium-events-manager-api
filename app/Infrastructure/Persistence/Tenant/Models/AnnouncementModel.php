<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnouncementModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'announcements';

    protected $fillable = [
        'id',
        'title',
        'body',
        'priority',
        'audience_type',
        'audience_ids',
        'status',
        'published_by',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'audience_ids' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'published_by');
    }

    /**
     * @return HasMany<AnnouncementReadModel, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementReadModel::class, 'announcement_id');
    }
}
