<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Gateways\Fiscal\FakeNFSeProvider;
use App\Infrastructure\Gateways\Fiscal\FocusNFeProvider;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentNFSeDocumentRepository;
use Application\Billing\Contracts\NFSeDocumentRepositoryInterface;
use Application\Billing\Contracts\NFSeProviderInterface;
use Illuminate\Support\ServiceProvider;

class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // NFSe Document Repository
        $this->app->bind(NFSeDocumentRepositoryInterface::class, EloquentNFSeDocumentRepository::class);

        // NFSe Provider (external service)
        $this->app->bind(NFSeProviderInterface::class, function () {
            $driver = config('fiscal.driver', 'fake');

            return match ($driver) {
                'focus_nfe' => new FocusNFeProvider(
                    token: config('fiscal.focus_nfe.token', ''),
                    baseUrl: config('fiscal.focus_nfe.base_url', 'https://homologacao.focusnfe.com.br'),
                    webhookSecret: config('fiscal.focus_nfe.webhook_secret', ''),
                ),
                default => new FakeNFSeProvider,
            };
        });
    }
}
