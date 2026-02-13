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
        Schema::connection($this->connection)->create('guests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reservation_id');
            $table->string('name', 255);
            $table->string('document', 30)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('vehicle_plate', 15)->nullable();
            $table->string('relationship', 50)->nullable();
            $table->string('status', 20);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->uuid('checked_in_by')->nullable();
            $table->uuid('denied_by')->nullable();
            $table->text('denied_reason')->nullable();
            $table->uuid('registered_by');
            $table->timestamps();

            $table->foreign('reservation_id')->references('id')->on('reservations')->restrictOnDelete();

            $table->index('reservation_id', 'idx_guests_reservation_id');
            $table->index('status', 'idx_guests_status');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE guests
                ADD CONSTRAINT guests_status_check
                CHECK (status IN ('registered', 'checked_in', 'checked_out', 'denied', 'no_show'))
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('guests');
    }
};
