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
        Schema::connection($this->connection)->create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('space_id');
            $table->uuid('unit_id');
            $table->uuid('resident_id');
            $table->string('status', 30);
            $table->string('title', 255)->nullable();
            $table->timestamp('start_datetime');
            $table->timestamp('end_datetime');
            $table->integer('expected_guests')->default(0);
            $table->text('notes')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('canceled_by')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->uuid('no_show_by')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->foreign('space_id')->references('id')->on('spaces')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('resident_id')->references('id')->on('residents')->restrictOnDelete();

            $table->index('space_id', 'idx_reservations_space_id');
            $table->index('unit_id', 'idx_reservations_unit_id');
            $table->index('resident_id', 'idx_reservations_resident_id');
            $table->index('status', 'idx_reservations_status');
            $table->index(['space_id', 'start_datetime', 'end_datetime'], 'idx_reservations_space_period');
            $table->index('start_datetime', 'idx_reservations_start');
        });

        // PostgreSQL exclusion constraint for conflict prevention (skip in SQLite tests)
        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::connection($this->connection)->statement("
                ALTER TABLE reservations
                ADD CONSTRAINT reservations_no_overlap_excl
                EXCLUDE USING GIST (
                    space_id WITH =,
                    tsrange(start_datetime, end_datetime) WITH &&
                )
                WHERE (status IN ('pending_approval', 'confirmed', 'in_progress'))
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('reservations');
    }
};
