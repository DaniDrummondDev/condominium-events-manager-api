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
        Schema::connection($this->connection)->create('pending_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 60);
            $table->string('name', 255);
            $table->string('type', 20);
            $table->string('admin_name', 255);
            $table->string('admin_email', 255);
            $table->string('admin_password_hash', 255);
            $table->string('admin_phone', 20)->nullable();
            $table->string('plan_slug', 100);
            $table->string('verification_token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('slug', 'idx_pending_registrations_slug');
            $table->index('admin_email', 'idx_pending_registrations_email');
            $table->index('expires_at', 'idx_pending_registrations_expires_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('pending_registrations');
    }
};
