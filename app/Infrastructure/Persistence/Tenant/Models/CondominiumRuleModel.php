<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CondominiumRuleModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'condominium_rules';

    protected $fillable = [
        'id',
        'title',
        'description',
        'category',
        'is_active',
        'order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TenantUserModel, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUserModel::class, 'created_by');
    }
}
