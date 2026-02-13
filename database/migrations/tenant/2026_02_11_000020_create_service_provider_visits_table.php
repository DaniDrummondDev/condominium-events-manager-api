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
        Schema::connection($this->connection)->create('service_provider_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('service_provider_id');
            $table->uuid('unit_id');
            $table->uuid('reservation_id')->nullable();
            $table->date('scheduled_date');
            $table->string('purpose', 500);
            $table->string('status', 20);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->uuid('checked_in_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('service_provider_id')->references('id')->on('service_providers')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('reservation_id')->references('id')->on('reservations')->nullOnDelete();

            $table->index('service_provider_id', 'idx_spv_service_provider_id');
            $table->index('unit_id', 'idx_spv_unit_id');
            $table->index('scheduled_date', 'idx_spv_scheduled_date');
            $table->index('status', 'idx_spv_status');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE service_provider_visits
                ADD CONSTRAINT spv_status_check
                CHECK (status IN ('scheduled', 'checked_in', 'checked_out', 'canceled', 'no_show'))
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('service_provider_visits');
    }
};
