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
        Schema::connection($this->connection)->create('plan_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_version_id');
            $table->string('billing_cycle', 20);
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->unsignedInteger('trial_days')->default(0);

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->onDelete('cascade');

            $table->unique(['plan_version_id', 'billing_cycle'], 'uq_plan_prices_version_cycle');
            $table->index('plan_version_id', 'idx_plan_prices_version');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('plan_prices');
    }
};
