<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceProviderVisitModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'service_provider_visits';

    protected $fillable = [
        'id',
        'service_provider_id',
        'unit_id',
        'reservation_id',
        'scheduled_date',
        'purpose',
        'status',
        'checked_in_at',
        'checked_out_at',
        'checked_in_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceProviderModel, $this>
     */
    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProviderModel::class, 'service_provider_id');
    }

    /**
     * @return BelongsTo<UnitModel, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitModel::class, 'unit_id');
    }

    /**
     * @return BelongsTo<ReservationModel, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ReservationModel::class, 'reservation_id');
    }
}
