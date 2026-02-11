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
        Schema::connection($this->connection)->create('tenant_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255);
            $table->string('name', 255);
            $table->string('phone', 20)->nullable();
            $table->string('role', 30);
            $table->string('status', 20)->default('invited');
            $table->string('mfa_secret', 255)->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('role', 'idx_tenant_users_role');
            $table->index('status', 'idx_tenant_users_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenant_users');
    }
};
