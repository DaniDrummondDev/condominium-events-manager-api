<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Billing;

use App\Infrastructure\Persistence\Platform\Models\NFSeDocumentModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNFSeByEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly string $nfseId,
    ) {
        $this->queue = 'notifications';
    }

    public function handle(): void
    {
        $nfse = NFSeDocumentModel::query()->find($this->nfseId);

        if ($nfse === null) {
            Log::warning('SendNFSeByEmailJob: NFSe not found', [
                'nfse_id' => $this->nfseId,
            ]);

            return;
        }

        if ($nfse->status !== 'authorized') {
            Log::info('SendNFSeByEmailJob: NFSe not authorized, skipping', [
                'nfse_id' => $this->nfseId,
                'status' => $nfse->status,
            ]);

            return;
        }

        $tenant = \App\Infrastructure\Persistence\Platform\Models\TenantModel::query()
            ->find($nfse->tenant_id);

        if ($tenant === null) {
            Log::warning('SendNFSeByEmailJob: tenant not found', [
                'tenant_id' => $nfse->tenant_id,
            ]);

            return;
        }

        $emailTo = $tenant->email_fiscal ?? null;

        if (empty($emailTo)) {
            Log::info('SendNFSeByEmailJob: no fiscal email configured for tenant', [
                'tenant_id' => $nfse->tenant_id,
            ]);

            return;
        }

        Mail::raw(
            $this->buildEmailBody($nfse),
            function ($message) use ($emailTo, $nfse) {
                $message->to($emailTo)
                    ->subject("NFSe {$nfse->nfse_number} - Nota Fiscal de Serviço");
            }
        );

        Log::info('SendNFSeByEmailJob: email sent', [
            'nfse_id' => $this->nfseId,
            'tenant_id' => $nfse->tenant_id,
            'email' => $emailTo,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNFSeByEmailJob: failed', [
            'nfse_id' => $this->nfseId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function buildEmailBody(NFSeDocumentModel $nfse): string
    {
        $totalFormatted = number_format((float) $nfse->total_amount, 2, ',', '.');

        return implode("\n", [
            'Nota Fiscal de Serviço Eletrônica (NFSe)',
            '',
            "Número: {$nfse->nfse_number}",
            "Código de Verificação: {$nfse->verification_code}",
            "Valor: R$ {$totalFormatted}",
            "Data de Competência: {$nfse->competence_date->format('d/m/Y')}",
            '',
            "Descrição: {$nfse->service_description}",
            '',
            $nfse->pdf_url ? "PDF: {$nfse->pdf_url}" : '',
            '',
            'Esta é uma mensagem automática. Não responda a este e-mail.',
        ]);
    }
}
