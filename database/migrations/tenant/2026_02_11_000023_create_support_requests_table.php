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
        Schema::connection($this->connection)->create('support_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_user_id');
            $table->string('subject', 255);
            $table->string('category', 100);
            $table->string('status', 20)->default('open');
            $table->string('priority', 10)->default('normal');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason', 20)->nullable();
            $table->timestamps();

            $table->foreign('tenant_user_id')->references('id')->on('tenant_users')->restrictOnDelete();

            $table->index('tenant_user_id', 'idx_sr_user');
            $table->index('status', 'idx_sr_status');
            $table->index('created_at', 'idx_sr_created_at');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE support_requests
                ADD CONSTRAINT support_requests_status_check
                CHECK (status IN ('open', 'in_progress', 'resolved', 'closed'))
            ");

            DB::connection($this->connection)->statement("
                ALTER TABLE support_requests
                ADD CONSTRAINT support_requests_priority_check
                CHECK (priority IN ('low', 'normal', 'high'))
            ");

            DB::connection($this->connection)->statement("
                ALTER TABLE support_requests
                ADD CONSTRAINT support_requests_closed_reason_check
                CHECK (closed_reason IS NULL OR closed_reason IN ('resolved', 'auto_closed', 'admin_closed'))
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('support_requests');
    }
};
