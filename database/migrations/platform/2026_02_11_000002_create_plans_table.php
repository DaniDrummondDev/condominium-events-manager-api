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
        Schema::connection($this->connection)->create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status', 'idx_plans_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('plans');
    }
};
