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
        Schema::connection($this->connection)->create('space_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->string('rule_key', 100);
            $table->string('rule_value', 255);
            $table->text('description')->nullable();

            $table->unique(['space_id', 'rule_key'], 'uq_space_rules_space_key');
            $table->index('space_id', 'idx_space_rules_space');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('space_rules');
    }
};
