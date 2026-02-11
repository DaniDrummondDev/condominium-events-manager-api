<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FeatureModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'features';

    protected $fillable = [
        'id',
        'code',
        'name',
        'type',
        'description',
    ];
}
