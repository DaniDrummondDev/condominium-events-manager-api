<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Webhook;

use Application\Billing\DTOs\WebhookPayloadDTO;
use Application\Billing\UseCases\HandlePaymentWebhook;
use Domain\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingWebhookController
{
    public function handle(Request $request, HandlePaymentWebhook $useCase): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $data = $request->all();

        try {
            $dto = new WebhookPayloadDTO(
                gateway: $data['gateway'] ?? 'stripe',
                eventType: $data['type'] ?? $data['event_type'] ?? 'unknown',
                gatewayTransactionId: $data['data']['object']['id']
                    ?? $data['gateway_transaction_id']
                    ?? '',
                status: $data['data']['object']['status']
                    ?? $data['status']
                    ?? 'unknown',
                amountInCents: (int) ($data['data']['object']['amount']
                    ?? $data['amount']
                    ?? 0),
                metadata: $data,
            );

            $useCase->execute($rawPayload, $signature, $dto);

            return new JsonResponse(['received' => true]);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
