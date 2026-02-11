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
        Schema::connection($this->connection)->create('violations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id');
            $table->uuid('tenant_user_id')->nullable();
            $table->uuid('reservation_id')->nullable();
            $table->uuid('rule_id')->nullable();
            $table->string('type', 30);
            $table->string('severity', 20);
            $table->text('description');
            $table->string('status', 20)->default('open');
            $table->boolean('is_automatic')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('upheld_by')->nullable();
            $table->timestamp('upheld_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->timestamps();

            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('tenant_user_id')->references('id')->on('tenant_users')->nullOnDelete();
            $table->foreign('reservation_id')->references('id')->on('reservations')->nullOnDelete();
            $table->foreign('rule_id')->references('id')->on('condominium_rules')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('tenant_users')->nullOnDelete();
            $table->foreign('upheld_by')->references('id')->on('tenant_users')->nullOnDelete();
            $table->foreign('revoked_by')->references('id')->on('tenant_users')->nullOnDelete();

            $table->index('unit_id', 'idx_violations_unit');
            $table->index('tenant_user_id', 'idx_violations_user');
            $table->index('reservation_id', 'idx_violations_reservation');
            $table->index('status', 'idx_violations_status');
            $table->index('type', 'idx_violations_type');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE violations
                ADD CONSTRAINT violations_type_check
                CHECK (type IN ('no_show', 'late_cancellation', 'capacity_exceeded', 'noise_complaint', 'damage', 'rule_violation', 'other'))
            ");

            DB::connection($this->connection)->statement("
                ALTER TABLE violations
                ADD CONSTRAINT violations_severity_check
                CHECK (severity IN ('low', 'medium', 'high', 'critical'))
            ");

            DB::connection($this->connection)->statement("
                ALTER TABLE violations
                ADD CONSTRAINT violations_status_check
                CHECK (status IN ('open', 'contested', 'upheld', 'revoked'))
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('violations');
    }
};
