<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanVersionModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'plan_versions';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'plan_id',
        'version',
        'price',
        'currency',
        'billing_cycle',
        'trial_days',
        'status',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'trial_days' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlanModel, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id');
    }

    /**
     * @return HasMany<PlanFeatureModel, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeatureModel::class, 'plan_version_id');
    }
}
