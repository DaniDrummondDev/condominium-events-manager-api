<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantUserModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'tenant_users';

    protected $fillable = [
        'id',
        'email',
        'password_hash',
        'name',
        'phone',
        'role',
        'status',
        'mfa_secret',
        'mfa_enabled',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'invitation_token',
        'invitation_expires_at',
    ];

    protected $hidden = [
        'password_hash',
        'mfa_secret',
        'invitation_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mfa_enabled' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
        ];
    }
}
