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
        Schema::connection($this->connection)->create('space_availabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->integer('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['space_id', 'day_of_week', 'start_time'], 'uq_sa_space_day_start');
            $table->index(['space_id', 'day_of_week'], 'idx_sa_space_day');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('space_availabilities');
    }
};
