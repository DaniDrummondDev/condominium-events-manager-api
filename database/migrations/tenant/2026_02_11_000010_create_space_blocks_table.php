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
        Schema::connection($this->connection)->create('space_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->string('reason', 255);
            $table->timestamp('start_datetime');
            $table->timestamp('end_datetime');
            $table->foreignUuid('blocked_by')->constrained('tenant_users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('space_id', 'idx_space_blocks_space');
            $table->index(['space_id', 'start_datetime', 'end_datetime'], 'idx_space_blocks_period');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('space_blocks');
    }
};
