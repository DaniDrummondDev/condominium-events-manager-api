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
        Schema::connection($this->connection)->create('tenant_feature_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('feature_id');
            $table->string('value', 255);
            $table->text('reason');
            $table->timestamp('expires_at')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('feature_id')
                ->references('id')
                ->on('features')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('platform_users')
                ->onDelete('restrict');

            $table->unique(['tenant_id', 'feature_id'], 'uq_tenant_feature_overrides');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenant_feature_overrides');
    }
};
