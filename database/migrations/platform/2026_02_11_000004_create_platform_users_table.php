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
        Schema::connection($this->connection)->create('platform_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255);
            $table->string('role', 30);
            $table->string('status', 20)->default('active');
            $table->string('mfa_secret', 255)->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('role', 'idx_platform_users_role');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('platform_users');
    }
};
