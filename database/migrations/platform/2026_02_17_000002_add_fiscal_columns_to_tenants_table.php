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
        Schema::connection($this->connection)->table('tenants', function (Blueprint $table) {
            $table->string('cnpj', 14)->unique()->nullable()->after('type');
            $table->string('razao_social', 255)->nullable()->after('cnpj');
            $table->jsonb('endereco')->nullable()->after('razao_social');
            $table->string('email_fiscal', 255)->nullable()->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('tenants', function (Blueprint $table) {
            $table->dropUnique(['cnpj']);
            $table->dropColumn(['cnpj', 'razao_social', 'endereco', 'email_fiscal']);
        });
    }
};
