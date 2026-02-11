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
        Schema::connection($this->connection)->create('condominium_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->text('description');
            $table->string('category', 100);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('tenant_users')->nullOnDelete();

            $table->index('category', 'idx_rules_category');
            $table->index('is_active', 'idx_rules_active');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('condominium_rules');
    }
};
