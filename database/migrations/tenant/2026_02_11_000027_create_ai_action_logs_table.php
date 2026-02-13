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
        Schema::connection($this->connection)->create('ai_action_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_user_id');
            $table->string('tool_name', 100);
            $table->jsonb('input_data');
            $table->jsonb('output_data')->nullable();
            $table->string('status', 20);
            $table->uuid('confirmed_by')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_user_id')
                ->references('id')
                ->on('tenant_users')
                ->restrictOnDelete();

            $table->foreign('confirmed_by')
                ->references('id')
                ->on('tenant_users')
                ->nullOnDelete();

            $table->index('tenant_user_id', 'idx_ai_actions_user');
            $table->index('status', 'idx_ai_actions_status');
        });

        // Add CHECK constraint on PostgreSQL
        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement(
                "ALTER TABLE ai_action_logs ADD CONSTRAINT chk_ai_action_logs_status CHECK (status IN ('proposed', 'confirmed', 'rejected', 'executed', 'failed'))"
            );
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ai_action_logs');
    }
};
