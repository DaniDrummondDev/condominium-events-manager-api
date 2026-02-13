<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_user_id')->nullable();
            $table->string('action', 100);
            $table->string('model', 100);
            $table->integer('tokens_input');
            $table->integer('tokens_output');
            $table->integer('latency_ms');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_user_id')
                ->references('id')
                ->on('tenant_users')
                ->nullOnDelete();

            $table->index('tenant_user_id', 'idx_ai_usage_user');
            $table->index('created_at', 'idx_ai_usage_created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ai_usage_logs');
    }
};
