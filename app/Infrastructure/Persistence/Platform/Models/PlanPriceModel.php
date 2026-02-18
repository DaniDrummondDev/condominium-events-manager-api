<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPriceModel extends Model
{
    use HasUuids;

    protected $table = 'plan_prices';

    protected $connection = 'platform';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'plan_version_id',
        'billing_cycle',
        'price',
        'currency',
        'trial_days',
    ];

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PlanVersionModel::class, 'plan_version_id');
    }
}
