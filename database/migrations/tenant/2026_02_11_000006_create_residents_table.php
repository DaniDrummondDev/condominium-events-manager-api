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
        Schema::connection($this->connection)->create('residents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id');
            $table->uuid('tenant_user_id');
            $table->string('role_in_unit', 20);
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('invited');
            $table->timestamp('moved_in_at')->nullable();
            $table->timestamp('moved_out_at')->nullable();
            $table->timestamps();

            $table->foreign('unit_id')
                ->references('id')
                ->on('units')
                ->restrictOnDelete();

            $table->foreign('tenant_user_id')
                ->references('id')
                ->on('tenant_users')
                ->restrictOnDelete();

            $table->index('unit_id', 'idx_residents_unit_id');
            $table->index('tenant_user_id', 'idx_residents_tenant_user_id');
            $table->index('status', 'idx_residents_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('residents');
    }
};
