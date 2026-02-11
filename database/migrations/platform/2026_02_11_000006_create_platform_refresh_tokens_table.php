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
        Schema::connection($this->connection)->create('platform_refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash', 128)->unique();
            $table->uuid('parent_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 500);
            $table->timestamp('created_at');

            $table->foreign('user_id')
                ->references('id')
                ->on('platform_users')
                ->onDelete('cascade');

            $table->index('user_id', 'idx_platform_refresh_tokens_user_id');
            $table->index('expires_at', 'idx_platform_refresh_tokens_expires_at');
        });

        // Self-referencing FK must be added after table creation (PostgreSQL requirement)
        Schema::connection($this->connection)->table('platform_refresh_tokens', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('platform_refresh_tokens')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('platform_refresh_tokens');
    }
};
