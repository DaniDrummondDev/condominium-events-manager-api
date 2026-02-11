<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'platform';

    public function up(): void
    {
        Schema::connection($this->connection)->create('platform_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_type', 50);
            $table->uuid('actor_id');
            $table->string('action', 100);
            $table->string('resource_type', 50);
            $table->uuid('resource_id')->nullable();
            $table->jsonb('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_id', 'idx_platform_audit_actor');
            $table->index('resource_type', 'idx_platform_audit_resource_type');
            $table->index('action', 'idx_platform_audit_action');
            $table->index('created_at', 'idx_platform_audit_created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('platform_audit_logs');
    }
};
