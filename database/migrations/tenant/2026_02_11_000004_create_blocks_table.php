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
        Schema::connection($this->connection)->create('blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('identifier', 50)->unique();
            $table->string('name', 255);
            $table->integer('floors')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status', 'idx_blocks_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('blocks');
    }
};
