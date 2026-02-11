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
        Schema::connection($this->connection)->create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('plan_version_id');
            $table->string('status', 20);
            $table->string('billing_cycle', 10);
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('grace_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('restrict');

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->onDelete('restrict');

            $table->index('tenant_id', 'idx_subscriptions_tenant');
            $table->index(['tenant_id', 'status'], 'idx_subscriptions_tenant_status');
            $table->index('status', 'idx_subscriptions_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('subscriptions');
    }
};
