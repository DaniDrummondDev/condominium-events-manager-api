<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformRefreshTokenModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'platform_refresh_tokens';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'token_hash',
        'parent_id',
        'expires_at',
        'used_at',
        'revoked_at',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
