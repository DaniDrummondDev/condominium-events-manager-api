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
        Schema::connection($this->connection)->create('penalties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('violation_id');
            $table->uuid('unit_id');
            $table->string('type', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('violation_id')->references('id')->on('violations')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('revoked_by')->references('id')->on('tenant_users')->nullOnDelete();

            $table->index('unit_id', 'idx_penalties_unit');
            $table->index('violation_id', 'idx_penalties_violation');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE penalties
                ADD CONSTRAINT penalties_type_check
                CHECK (type IN ('warning', 'temporary_block', 'permanent_block'))
            ");

            DB::connection($this->connection)->statement("
                ALTER TABLE penalties
                ADD CONSTRAINT penalties_status_check
                CHECK (status IN ('active', 'expired', 'revoked'))
            ");

            DB::connection($this->connection)->statement("
                CREATE INDEX idx_penalties_active ON penalties (unit_id, status) WHERE status = 'active'
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('penalties');
    }
};
