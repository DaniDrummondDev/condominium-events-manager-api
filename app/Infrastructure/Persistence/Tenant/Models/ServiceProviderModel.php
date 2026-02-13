<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ServiceProviderModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'service_providers';

    protected $fillable = [
        'id',
        'company_name',
        'name',
        'document',
        'phone',
        'service_type',
        'status',
        'notes',
        'created_by',
    ];
}
