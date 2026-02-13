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
        Schema::connection($this->connection)->create('support_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('support_request_id');
            $table->uuid('sender_id');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at');

            $table->foreign('support_request_id')->references('id')->on('support_requests')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('tenant_users')->restrictOnDelete();

            $table->index('support_request_id', 'idx_sm_request');
            $table->index(['support_request_id', 'created_at'], 'idx_sm_created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('support_messages');
    }
};
