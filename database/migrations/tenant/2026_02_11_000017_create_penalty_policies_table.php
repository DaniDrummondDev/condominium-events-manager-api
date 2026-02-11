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
        Schema::connection($this->connection)->create('penalty_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('violation_type', 30);
            $table->integer('occurrence_threshold');
            $table->string('penalty_type', 20);
            $table->integer('block_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('violation_type', 'idx_pp_violation_type');
            $table->index('is_active', 'idx_pp_active');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE penalty_policies
                ADD CONSTRAINT penalty_policies_violation_type_check
                CHECK (violation_type IN ('no_show', 'late_cancellation', 'capacity_exceeded', 'noise_complaint', 'damage', 'rule_violation', 'other'))
            ");

            DB::connection($this->connection)->statement("
                ALTER TABLE penalty_policies
                ADD CONSTRAINT penalty_policies_penalty_type_check
                CHECK (penalty_type IN ('warning', 'temporary_block', 'permanent_block'))
            ");

            DB::connection($this->connection)->statement('
                ALTER TABLE penalty_policies
                ADD CONSTRAINT penalty_policies_occurrence_threshold_check
                CHECK (occurrence_threshold > 0)
            ');

            DB::connection($this->connection)->statement('
                ALTER TABLE penalty_policies
                ADD CONSTRAINT penalty_policies_block_days_check
                CHECK (block_days IS NULL OR block_days > 0)
            ');
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('penalty_policies');
    }
};
