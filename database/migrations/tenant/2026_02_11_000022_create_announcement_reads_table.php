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
        Schema::connection($this->connection)->create('announcement_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('announcement_id');
            $table->uuid('tenant_user_id');
            $table->timestamp('read_at');

            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
            $table->foreign('tenant_user_id')->references('id')->on('tenant_users')->cascadeOnDelete();

            $table->unique(['announcement_id', 'tenant_user_id'], 'idx_ar_announcement_user');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('announcement_reads');
    }
};
