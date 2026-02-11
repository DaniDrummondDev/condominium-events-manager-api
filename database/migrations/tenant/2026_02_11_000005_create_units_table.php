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
        Schema::connection($this->connection)->create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('block_id')->nullable();
            $table->string('number', 50);
            $table->integer('floor')->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->default('active');
            $table->boolean('is_occupied')->default(false);
            $table->timestamps();

            $table->foreign('block_id')
                ->references('id')
                ->on('blocks')
                ->nullOnDelete();

            $table->unique(['block_id', 'number'], 'uq_units_block_number');
            $table->index('status', 'idx_units_status');
            $table->index('block_id', 'idx_units_block_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('units');
    }
};
