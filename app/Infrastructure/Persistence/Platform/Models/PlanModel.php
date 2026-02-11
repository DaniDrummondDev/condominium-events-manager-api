<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'plans';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'status',
    ];

    /**
     * @return HasMany<PlanVersionModel, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersionModel::class, 'plan_id');
    }
}
