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
        Schema::connection($this->connection)->create('plan_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_version_id');
            $table->string('feature_key', 100);
            $table->string('value', 255);
            $table->string('type', 20);

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->onDelete('cascade');

            $table->unique(['plan_version_id', 'feature_key'], 'uq_plan_features_version_key');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('plan_features');
    }
};
