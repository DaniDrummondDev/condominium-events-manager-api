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
        Schema::connection($this->connection)->create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 60)->unique();
            $table->string('name', 255);
            $table->string('type', 20);
            $table->string('status', 20)->default('prospect');
            $table->jsonb('config')->nullable();
            $table->string('database_name', 100)->unique()->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_tenants_status');
            $table->index('type', 'idx_tenants_type');
            $table->index('created_at', 'idx_tenants_created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenants');
    }
};
