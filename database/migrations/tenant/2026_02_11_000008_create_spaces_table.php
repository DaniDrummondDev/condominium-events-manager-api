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
        Schema::connection($this->connection)->create('spaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->string('status', 20)->default('active');
            $table->integer('capacity');
            $table->boolean('requires_approval')->default(false);
            $table->integer('max_duration_hours')->nullable();
            $table->integer('max_advance_days')->default(30);
            $table->integer('min_advance_hours')->default(24);
            $table->integer('cancellation_deadline_hours')->default(24);
            $table->timestamps();

            $table->index('status', 'idx_spaces_status');
            $table->index('type', 'idx_spaces_type');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spaces');
    }
};
