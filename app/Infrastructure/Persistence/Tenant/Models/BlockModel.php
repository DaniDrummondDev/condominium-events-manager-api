<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlockModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'blocks';

    protected $fillable = [
        'id',
        'identifier',
        'name',
        'floors',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'floors' => 'integer',
        ];
    }

    /**
     * @return HasMany<UnitModel, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(UnitModel::class, 'block_id');
    }
}
