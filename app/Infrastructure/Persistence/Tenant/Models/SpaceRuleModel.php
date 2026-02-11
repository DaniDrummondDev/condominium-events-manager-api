<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceRuleModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'space_rules';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'space_id',
        'rule_key',
        'rule_value',
        'description',
    ];

    /**
     * @return BelongsTo<SpaceModel, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(SpaceModel::class, 'space_id');
    }
}
