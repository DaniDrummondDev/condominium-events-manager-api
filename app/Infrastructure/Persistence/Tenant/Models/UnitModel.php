<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'units';

    protected $fillable = [
        'id',
        'block_id',
        'number',
        'floor',
        'type',
        'status',
        'is_occupied',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'is_occupied' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BlockModel, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(BlockModel::class, 'block_id');
    }

    /**
     * @return HasMany<ResidentModel, $this>
     */
    public function residents(): HasMany
    {
        return $this->hasMany(ResidentModel::class, 'unit_id');
    }
}
