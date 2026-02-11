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
        Schema::connection($this->connection)->create('gateway_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('gateway', 50);
            $table->string('event_type', 100);
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->string('idempotency_key', 255)->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('set null');

            $table->index('tenant_id', 'idx_gateway_events_tenant');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('gateway_events');
    }
};
