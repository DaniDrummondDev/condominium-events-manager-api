<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'reservations';

    protected $fillable = [
        'id',
        'space_id',
        'unit_id',
        'resident_id',
        'status',
        'title',
        'start_datetime',
        'end_datetime',
        'expected_guests',
        'notes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'canceled_by',
        'canceled_at',
        'cancellation_reason',
        'completed_at',
        'no_show_at',
        'no_show_by',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'expected_guests' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'canceled_at' => 'datetime',
            'completed_at' => 'datetime',
            'no_show_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SpaceModel, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(SpaceModel::class, 'space_id');
    }

    /**
     * @return BelongsTo<UnitModel, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitModel::class, 'unit_id');
    }

    /**
     * @return BelongsTo<ResidentModel, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(ResidentModel::class, 'resident_id');
    }
}
