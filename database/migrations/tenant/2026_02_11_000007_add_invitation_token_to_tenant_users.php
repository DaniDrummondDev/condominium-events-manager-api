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
        Schema::connection($this->connection)->table('tenant_users', function (Blueprint $table) {
            $table->string('invitation_token', 255)->nullable()->unique()->after('last_login_at');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('tenant_users', function (Blueprint $table) {
            $table->dropColumn(['invitation_token', 'invitation_expires_at']);
        });
    }
};
