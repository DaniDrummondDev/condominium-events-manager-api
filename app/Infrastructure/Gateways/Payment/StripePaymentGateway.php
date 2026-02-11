<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateways\Payment;

use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\DTOs\ChargeRequestDTO;
use Application\Billing\DTOs\ChargeResultDTO;
use Application\Billing\DTOs\RefundRequestDTO;
use Application\Billing\DTOs\RefundResultDTO;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
    ) {
        Stripe::setApiKey($this->secretKey);
    }

    public function charge(ChargeRequestDTO $request): ChargeResultDTO
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amountInCents,
                'currency' => strtolower($request->currency),
                'payment_method' => $request->paymentMethodToken,
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'metadata' => array_merge($request->metadata, [
                    'invoice_id' => $request->invoiceId,
                ]),
            ]);

            $success = in_array($paymentIntent->status, ['succeeded', 'requires_capture']);

            return new ChargeResultDTO(
                success: $success,
                gatewayTransactionId: $paymentIntent->id,
                status: $paymentIntent->status,
                errorMessage: $success ? null : "Payment status: {$paymentIntent->status}",
            );
        } catch (ApiErrorException $e) {
            return new ChargeResultDTO(
                success: false,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function refund(RefundRequestDTO $request): RefundResultDTO
    {
        try {
            $refund = Refund::create([
                'payment_intent' => $request->gatewayTransactionId,
                'amount' => $request->amountInCents,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'reason' => $request->reason,
                ],
            ]);

            return new RefundResultDTO(
                success: $refund->status === 'succeeded',
                refundId: $refund->id,
                errorMessage: $refund->status !== 'succeeded'
                    ? "Refund status: {$refund->status}"
                    : null,
            );
        } catch (ApiErrorException $e) {
            return new RefundResultDTO(
                success: false,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return false;
        }

        try {
            Webhook::constructEvent($payload, $signature, $this->webhookSecret);

            return true;
        } catch (SignatureVerificationException) {
            return false;
        }
    }
}
