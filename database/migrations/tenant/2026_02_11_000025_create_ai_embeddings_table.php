<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ai_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_type', 100);
            $table->uuid('source_id');
            $table->integer('chunk_index')->default(0);
            $table->text('content_text');
            $table->text('embedding');
            $table->string('model_version', 50);
            $table->string('content_hash', 64);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->unique(['source_type', 'source_id', 'chunk_index', 'model_version'], 'uq_ai_embeddings_source_chunk');
            $table->unique(['content_hash', 'model_version'], 'uq_ai_embeddings_dedupe');
            $table->index(['source_type', 'source_id'], 'idx_ai_embeddings_source');
        });

        // Add pgvector VECTOR column and IVFFlat index when running on PostgreSQL
        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            $dimensions = (int) config('ai.embedding_dimensions', 1536);

            DB::connection($this->connection)->statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::connection($this->connection)->statement(
                "ALTER TABLE ai_embeddings ALTER COLUMN embedding TYPE VECTOR({$dimensions}) USING embedding::VECTOR({$dimensions})"
            );
            DB::connection($this->connection)->statement(
                'CREATE INDEX idx_ai_embeddings_vector ON ai_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)'
            );
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ai_embeddings');
    }
};
