<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Webhook;

use App\Infrastructure\Jobs\Billing\SendNFSeByEmailJob;
use Application\Billing\DTOs\NFSeWebhookDTO;
use Application\Billing\UseCases\HandleNFSeWebhook;
use Domain\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalWebhookController
{
    public function handle(Request $request, HandleNFSeWebhook $useCase): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Webhook-Signature', '');
        $data = $request->all();

        try {
            $dto = new NFSeWebhookDTO(
                providerRef: $data['ref'] ?? $data['provider_ref'] ?? '',
                status: $data['status'] ?? 'unknown',
                nfseNumber: $data['numero'] ?? $data['nfse_number'] ?? null,
                verificationCode: $data['codigo_verificacao'] ?? $data['verification_code'] ?? null,
                pdfUrl: $data['url'] ?? $data['caminho_xml_nota_fiscal'] ?? null,
                xmlContent: null,
                errorMessage: $data['mensagem'] ?? $data['erros'][0]['mensagem'] ?? null,
                rawPayload: $data,
            );

            $useCase->execute($rawPayload, $signature, $dto);

            // If authorized, dispatch email
            if (in_array($dto->status, ['authorized', 'autorizada'], true)) {
                $nfseRepo = app(\Application\Billing\Contracts\NFSeDocumentRepositoryInterface::class);
                $nfse = $nfseRepo->findByProviderRef($dto->providerRef);

                if ($nfse !== null) {
                    SendNFSeByEmailJob::dispatch($nfse->id()->value());
                }
            }

            return new JsonResponse(['received' => true]);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
