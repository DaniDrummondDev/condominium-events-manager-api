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
        Schema::connection($this->connection)->create('nfse_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->string('status', 20)->default('draft');
            $table->string('provider_ref', 255)->nullable();
            $table->string('nfse_number', 50)->nullable();
            $table->string('verification_code', 100)->nullable();
            $table->text('service_description');
            $table->date('competence_date');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('iss_rate', 5, 4);
            $table->decimal('iss_amount', 10, 2);
            $table->text('pdf_url')->nullable();
            $table->text('xml_content')->nullable();
            $table->jsonb('provider_response')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('idempotency_key', 255)->unique();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->restrictOnDelete();

            $table->index('tenant_id', 'idx_nfse_tenant');
            $table->index('invoice_id', 'idx_nfse_invoice');
            $table->index('status', 'idx_nfse_status');
            $table->index('provider_ref', 'idx_nfse_provider_ref');
            $table->unique(['tenant_id', 'nfse_number'], 'uniq_nfse_tenant_number');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('nfse_documents');
    }
};
