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
        Schema::connection($this->connection)->create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subscription_id');
            $table->string('invoice_number', 50);
            $table->string('status', 20)->default('draft');
            $table->string('currency', 3)->default('BRL');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('restrict');

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('restrict');

            $table->unique(['tenant_id', 'invoice_number'], 'uq_invoices_tenant_number');
            $table->index('tenant_id', 'idx_invoices_tenant');
            $table->index('subscription_id', 'idx_invoices_subscription');
            $table->index('status', 'idx_invoices_status');
            $table->index('due_date', 'idx_invoices_due_date');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('invoices');
    }
};
