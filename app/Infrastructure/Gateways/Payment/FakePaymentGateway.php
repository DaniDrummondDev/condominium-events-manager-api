<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateways\Payment;

use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\DTOs\ChargeRequestDTO;
use Application\Billing\DTOs\ChargeResultDTO;
use Application\Billing\DTOs\RefundRequestDTO;
use Application\Billing\DTOs\RefundResultDTO;

class FakePaymentGateway implements PaymentGatewayInterface
{
    private bool $shouldSucceed = true;

    private string $failureMessage = 'Payment declined';

    /** @var array<ChargeRequestDTO> */
    private array $charges = [];

    /** @var array<RefundRequestDTO> */
    private array $refunds = [];

    public function charge(ChargeRequestDTO $request): ChargeResultDTO
    {
        $this->charges[] = $request;

        if (! $this->shouldSucceed) {
            return new ChargeResultDTO(
                success: false,
                errorMessage: $this->failureMessage,
            );
        }

        return new ChargeResultDTO(
            success: true,
            gatewayTransactionId: 'fake_tx_'.bin2hex(random_bytes(8)),
            status: 'paid',
        );
    }

    public function refund(RefundRequestDTO $request): RefundResultDTO
    {
        $this->refunds[] = $request;

        if (! $this->shouldSucceed) {
            return new RefundResultDTO(
                success: false,
                errorMessage: $this->failureMessage,
            );
        }

        return new RefundResultDTO(
            success: true,
            refundId: 'fake_refund_'.bin2hex(random_bytes(8)),
        );
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        return $signature === 'valid_signature';
    }

    public function setShouldSucceed(bool $shouldSucceed): void
    {
        $this->shouldSucceed = $shouldSucceed;
    }

    public function setFailureMessage(string $message): void
    {
        $this->failureMessage = $message;
    }

    /**
     * @return array<ChargeRequestDTO>
     */
    public function getCharges(): array
    {
        return $this->charges;
    }

    /**
     * @return array<RefundRequestDTO>
     */
    public function getRefunds(): array
    {
        return $this->refunds;
    }

    public function reset(): void
    {
        $this->shouldSucceed = true;
        $this->failureMessage = 'Payment declined';
        $this->charges = [];
        $this->refunds = [];
    }
}
