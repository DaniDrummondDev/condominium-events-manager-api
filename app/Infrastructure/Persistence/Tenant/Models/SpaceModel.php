<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpaceModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'spaces';

    protected $fillable = [
        'id',
        'name',
        'description',
        'type',
        'status',
        'capacity',
        'requires_approval',
        'max_duration_hours',
        'max_advance_days',
        'min_advance_hours',
        'cancellation_deadline_hours',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'requires_approval' => 'boolean',
            'max_duration_hours' => 'integer',
            'max_advance_days' => 'integer',
            'min_advance_hours' => 'integer',
            'cancellation_deadline_hours' => 'integer',
        ];
    }

    /**
     * @return HasMany<SpaceAvailabilityModel, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(SpaceAvailabilityModel::class, 'space_id');
    }

    /**
     * @return HasMany<SpaceBlockModel, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(SpaceBlockModel::class, 'space_id');
    }

    /**
     * @return HasMany<SpaceRuleModel, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(SpaceRuleModel::class, 'space_id');
    }
}
