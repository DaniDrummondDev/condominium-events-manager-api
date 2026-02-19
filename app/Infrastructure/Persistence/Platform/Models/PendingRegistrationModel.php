<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PendingRegistrationModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'pending_registrations';

    protected $fillable = [
        'id',
        'slug',
        'name',
        'type',
        'admin_name',
        'admin_email',
        'admin_password_hash',
        'admin_phone',
        'plan_slug',
        'verification_token_hash',
        'expires_at',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
