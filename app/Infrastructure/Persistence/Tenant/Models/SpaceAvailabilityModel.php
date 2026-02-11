<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceAvailabilityModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'space_availabilities';

    protected $fillable = [
        'id',
        'space_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SpaceModel, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(SpaceModel::class, 'space_id');
    }
}
