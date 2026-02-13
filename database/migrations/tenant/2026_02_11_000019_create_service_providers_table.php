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
        Schema::connection($this->connection)->create('service_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name', 255)->nullable();
            $table->string('name', 255);
            $table->string('document', 30);
            $table->string('phone', 20)->nullable();
            $table->string('service_type', 30);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->unique('document', 'uq_service_providers_document');
            $table->index('status', 'idx_service_providers_status');
            $table->index('service_type', 'idx_service_providers_service_type');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE service_providers
                ADD CONSTRAINT service_providers_status_check
                CHECK (status IN ('active', 'inactive', 'blocked'))
            ");
            DB::connection($this->connection)->statement("
                ALTER TABLE service_providers
                ADD CONSTRAINT service_providers_service_type_check
                CHECK (service_type IN ('buffet', 'cleaning', 'decoration', 'dj', 'security', 'maintenance', 'moving', 'other'))
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('service_providers');
    }
};
