<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlanFeatureModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'plan_features';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'plan_version_id',
        'feature_key',
        'value',
        'type',
    ];
}
