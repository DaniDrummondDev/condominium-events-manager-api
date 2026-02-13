<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'guests';

    protected $fillable = [
        'id',
        'reservation_id',
        'name',
        'document',
        'phone',
        'vehicle_plate',
        'relationship',
        'status',
        'checked_in_at',
        'checked_out_at',
        'checked_in_by',
        'denied_by',
        'denied_reason',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReservationModel, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ReservationModel::class, 'reservation_id');
    }
}
