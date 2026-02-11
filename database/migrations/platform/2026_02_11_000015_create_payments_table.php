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
        Schema::connection($this->connection)->create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->string('gateway', 50);
            $table->string('gateway_transaction_id', 255)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('status', 20)->default('pending');
            $table->string('method', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('restrict');

            $table->index('invoice_id', 'idx_payments_invoice');
            $table->index(['gateway', 'gateway_transaction_id'], 'idx_payments_gateway_tx');
            $table->index('status', 'idx_payments_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('payments');
    }
};
