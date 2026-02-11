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
        Schema::connection($this->connection)->create('tenant_mfa_recovery_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('code_hash', 128);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');

            $table->foreign('user_id')
                ->references('id')
                ->on('tenant_users')
                ->onDelete('cascade');

            $table->index('user_id', 'idx_tenant_mfa_recovery_user_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenant_mfa_recovery_codes');
    }
};
