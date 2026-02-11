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
        Schema::connection($this->connection)->create('dunning_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->unsignedInteger('max_retries')->default(3);
            $table->json('retry_intervals');
            $table->unsignedInteger('suspend_after_days')->default(15);
            $table->unsignedInteger('cancel_after_days')->default(30);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('dunning_policies');
    }
};
