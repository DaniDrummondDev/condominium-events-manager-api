<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AIEmbeddingModel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'ai_embeddings';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'source_type',
        'source_id',
        'chunk_index',
        'content_text',
        'embedding',
        'model_version',
        'content_hash',
        'metadata',
        'created_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
