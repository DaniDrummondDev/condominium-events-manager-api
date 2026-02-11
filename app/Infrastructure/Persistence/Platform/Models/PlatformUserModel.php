<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformUserModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'platform_users';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password_hash',
        'role',
        'status',
        'mfa_secret',
        'mfa_enabled',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
        'mfa_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mfa_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
