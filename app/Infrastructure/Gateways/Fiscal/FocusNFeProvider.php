<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateways\Fiscal;

use Application\Billing\Contracts\NFSeProviderInterface;
use Application\Billing\DTOs\NFSeRequestDTO;
use Application\Billing\DTOs\NFSeResultDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FocusNFeProvider implements NFSeProviderInterface
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl,
        private readonly string $webhookSecret,
    ) {}

    public function emit(NFSeRequestDTO $request): NFSeResultDTO
    {
        try {
            $payload = $this->buildEmitPayload($request);

            $response = Http::withBasicAuth($this->token, '')
                ->timeout(30)
                ->post("{$this->baseUrl}/v2/nfse?ref={$request->referenceId}", $payload);

            $data = $response->json() ?? [];

            Log::info('FocusNFeProvider: emit response', [
                'ref' => $request->referenceId,
                'status_code' => $response->status(),
                'status' => $data['status'] ?? 'unknown',
            ]);

            if ($response->successful()) {
                return new NFSeResultDTO(
                    success: true,
                    providerRef: $request->referenceId,
                    status: $data['status'] ?? 'processing',
                    nfseNumber: $data['numero'] ?? null,
                    verificationCode: $data['codigo_verificacao'] ?? null,
                    pdfUrl: $data['url'] ?? $data['caminho_xml_nota_fiscal'] ?? null,
                    xmlContent: null,
                    providerResponse: $data,
                );
            }

            return new NFSeResultDTO(
                success: false,
                providerRef: $request->referenceId,
                status: $data['status'] ?? 'error',
                errorMessage: $data['mensagem'] ?? $data['erros'][0]['mensagem'] ?? 'Request failed',
                providerResponse: $data,
            );
        } catch (\Throwable $e) {
            Log::error('FocusNFeProvider: emit failed', [
                'ref' => $request->referenceId,
                'error' => $e->getMessage(),
            ]);

            return new NFSeResultDTO(
                success: false,
                providerRef: $request->referenceId,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function cancel(string $providerRef, string $reason): NFSeResultDTO
    {
        try {
            $response = Http::withBasicAuth($this->token, '')
                ->timeout(30)
                ->delete("{$this->baseUrl}/v2/nfse/{$providerRef}", [
                    'justificativa' => $reason,
                ]);

            $data = $response->json() ?? [];

            if ($response->successful()) {
                return new NFSeResultDTO(
                    success: true,
                    providerRef: $providerRef,
                    status: 'cancelled',
                    providerResponse: $data,
                );
            }

            return new NFSeResultDTO(
                success: false,
                providerRef: $providerRef,
                errorMessage: $data['mensagem'] ?? 'Cancel request failed',
                providerResponse: $data,
            );
        } catch (\Throwable $e) {
            Log::error('FocusNFeProvider: cancel failed', [
                'ref' => $providerRef,
                'error' => $e->getMessage(),
            ]);

            return new NFSeResultDTO(
                success: false,
                providerRef: $providerRef,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function getStatus(string $providerRef): NFSeResultDTO
    {
        try {
            $response = Http::withBasicAuth($this->token, '')
                ->timeout(30)
                ->get("{$this->baseUrl}/v2/nfse/{$providerRef}");

            $data = $response->json() ?? [];

            return new NFSeResultDTO(
                success: $response->successful(),
                providerRef: $providerRef,
                status: $data['status'] ?? 'unknown',
                nfseNumber: $data['numero'] ?? null,
                verificationCode: $data['codigo_verificacao'] ?? null,
                pdfUrl: $data['url'] ?? null,
                providerResponse: $data,
            );
        } catch (\Throwable $e) {
            return new NFSeResultDTO(
                success: false,
                providerRef: $providerRef,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmitPayload(NFSeRequestDTO $request): array
    {
        return [
            'data_emissao' => $request->competenceDate,
            'prestador' => [
                'cnpj' => $request->emitter['cnpj'] ?? '',
                'inscricao_municipal' => $request->emitter['inscricao_municipal'] ?? '',
                'codigo_municipio' => $request->emitter['codigo_municipio'] ?? '',
            ],
            'tomador' => [
                'cnpj' => $request->recipient['cnpj'] ?? null,
                'razao_social' => $request->recipient['razao_social'] ?? null,
                'endereco' => $request->recipient['endereco'] ?? null,
                'email' => $request->recipient['email'] ?? null,
            ],
            'servico' => [
                'aliquota' => $request->issRate / 100,
                'discriminacao' => $request->serviceDescription,
                'iss_retido' => false,
                'item_lista_servico' => $request->emitter['codigo_servico'] ?? '',
                'codigo_cnae' => $request->emitter['cnae'] ?? '',
                'codigo_tributario_municipio' => $request->emitter['codigo_servico'] ?? '',
                'valor_servicos' => $request->totalAmountInCents / 100,
            ],
        ];
    }
}
