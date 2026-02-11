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
        Schema::connection($this->connection)->create('violation_contestations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('violation_id');
            $table->uuid('tenant_user_id');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->text('response')->nullable();
            $table->uuid('responded_by')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('violation_id')->references('id')->on('violations')->restrictOnDelete();
            $table->foreign('tenant_user_id')->references('id')->on('tenant_users')->restrictOnDelete();
            $table->foreign('responded_by')->references('id')->on('tenant_users')->nullOnDelete();

            $table->index('violation_id', 'idx_vc_violation');
            $table->index('status', 'idx_vc_status');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE violation_contestations
                ADD CONSTRAINT violation_contestations_status_check
                CHECK (status IN ('pending', 'accepted', 'rejected'))
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('violation_contestations');
    }
};
