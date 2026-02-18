<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'platform';

    public function up(): void
    {
        DB::connection($this->connection)
            ->table('plan_versions')
            ->orderBy('created_at')
            ->each(function (object $version) {
                DB::connection($this->connection)->table('plan_prices')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'plan_version_id' => $version->id,
                    'billing_cycle' => $version->billing_cycle,
                    'price' => $version->price,
                    'currency' => $version->currency,
                    'trial_days' => $version->trial_days,
                ]);
            });

        Schema::connection($this->connection)->table('plan_versions', function (Blueprint $table) {
            $table->dropColumn(['price', 'currency', 'billing_cycle', 'trial_days']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plan_versions', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->string('billing_cycle', 10)->default('monthly');
            $table->unsignedInteger('trial_days')->default(0);
        });

        DB::connection($this->connection)
            ->table('plan_prices')
            ->orderBy('plan_version_id')
            ->each(function (object $price) {
                DB::connection($this->connection)
                    ->table('plan_versions')
                    ->where('id', $price->plan_version_id)
                    ->update([
                        'price' => $price->price,
                        'currency' => $price->currency,
                        'billing_cycle' => $price->billing_cycle,
                        'trial_days' => $price->trial_days,
                    ]);
            });
    }
};
