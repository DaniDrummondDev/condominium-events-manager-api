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
        Schema::connection($this->connection)->create('plan_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->unsignedInteger('version');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('billing_cycle', 10);
            $table->unsignedInteger('trial_days')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->onDelete('restrict');

            $table->index('plan_id', 'idx_plan_versions_plan');
            $table->index('status', 'idx_plan_versions_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('plan_versions');
    }
};
