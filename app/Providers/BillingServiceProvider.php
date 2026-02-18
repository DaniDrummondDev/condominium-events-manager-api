<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Gateways\Payment\FakePaymentGateway;
use App\Infrastructure\Gateways\Payment\StripePaymentGateway;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentDunningPolicyRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentFeatureRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentGatewayEventRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentInvoiceRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentPaymentRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentPlanFeatureRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentPlanPriceRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentPlanRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentPlanVersionRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentSubscriptionRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentTenantFeatureOverrideRepository;
use App\Infrastructure\Persistence\Platform\Repositories\SequentialInvoiceNumberGenerator;
use App\Infrastructure\Services\Billing\CachedFeatureResolver;
use Application\Billing\Contracts\DunningPolicyRepositoryInterface;
use Application\Billing\Contracts\FeatureRepositoryInterface;
use Application\Billing\Contracts\FeatureResolverInterface;
use Application\Billing\Contracts\GatewayEventRepositoryInterface;
use Application\Billing\Contracts\InvoiceNumberGeneratorInterface;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\Contracts\PlanFeatureRepositoryInterface;
use Application\Billing\Contracts\PlanPriceRepositoryInterface;
use Application\Billing\Contracts\PlanRepositoryInterface;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\Contracts\TenantFeatureOverrideRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repositories
        $this->app->bind(PlanRepositoryInterface::class, EloquentPlanRepository::class);
        $this->app->bind(PlanVersionRepositoryInterface::class, EloquentPlanVersionRepository::class);
        $this->app->bind(FeatureRepositoryInterface::class, EloquentFeatureRepository::class);
        $this->app->bind(PlanFeatureRepositoryInterface::class, EloquentPlanFeatureRepository::class);
        $this->app->bind(PlanPriceRepositoryInterface::class, EloquentPlanPriceRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, EloquentSubscriptionRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->bind(DunningPolicyRepositoryInterface::class, EloquentDunningPolicyRepository::class);
        $this->app->bind(GatewayEventRepositoryInterface::class, EloquentGatewayEventRepository::class);
        $this->app->bind(TenantFeatureOverrideRepositoryInterface::class, EloquentTenantFeatureOverrideRepository::class);

        // Invoice Number Generator
        $this->app->bind(InvoiceNumberGeneratorInterface::class, SequentialInvoiceNumberGenerator::class);

        // Feature Resolver
        $this->app->bind(FeatureResolverInterface::class, CachedFeatureResolver::class);

        // Payment Gateway
        $this->app->bind(PaymentGatewayInterface::class, function () {
            $gateway = config('billing.gateway', 'fake');

            return match ($gateway) {
                'stripe' => new StripePaymentGateway(
                    secretKey: config('billing.stripe.secret', ''),
                    webhookSecret: config('billing.stripe.webhook_secret', ''),
                ),
                default => new FakePaymentGateway,
            };
        });
    }
}
