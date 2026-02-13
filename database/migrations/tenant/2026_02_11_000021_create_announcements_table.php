<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->text('body');
            $table->string('priority', 10)->default('normal');
            $table->string('audience_type', 10);
            $table->jsonb('audience_ids')->nullable();
            $table->string('status', 20)->default('published');
            $table->uuid('published_by');
            $table->timestamp('published_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('published_by')->references('id')->on('tenant_users')->restrictOnDelete();

            $table->index('published_at', 'idx_announcements_published_at');
            $table->index('priority', 'idx_announcements_priority');
            $table->index('audience_type', 'idx_announcements_audience');
            $table->index('status', 'idx_announcements_status');
        });

        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            DB::connection($this->connection)->statement("
                ALTER TABLE announcements
                ADD CONSTRAINT announcements_priority_check
                CHECK (priority IN ('low', 'normal', 'high', 'urgent'))
            ");

            DB::connection($this->connection)->statement("
                ALTER TABLE announcements
                ADD CONSTRAINT announcements_audience_type_check
                CHECK (audience_type IN ('all', 'block', 'units'))
            ");

            DB::connection($this->connection)->statement("
                ALTER TABLE announcements
                ADD CONSTRAINT announcements_status_check
                CHECK (status IN ('draft', 'published', 'archived'))
            ");
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('announcements');
    }
};
