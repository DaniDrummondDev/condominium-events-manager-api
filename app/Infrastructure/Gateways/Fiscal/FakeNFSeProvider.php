<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateways\Fiscal;

use Application\Billing\Contracts\NFSeProviderInterface;
use Application\Billing\DTOs\NFSeRequestDTO;
use Application\Billing\DTOs\NFSeResultDTO;

class FakeNFSeProvider implements NFSeProviderInterface
{
    private bool $shouldSucceed = true;

    private string $failureMessage = 'Fiscal emission failed';

    private bool $authorizeImmediately = true;

    /** @var array<NFSeRequestDTO> */
    private array $emissions = [];

    /** @var array<array{ref: string, reason: string}> */
    private array $cancellations = [];

    public function emit(NFSeRequestDTO $request): NFSeResultDTO
    {
        $this->emissions[] = $request;

        if (! $this->shouldSucceed) {
            return new NFSeResultDTO(
                success: false,
                providerRef: $request->referenceId,
                status: 'error',
                errorMessage: $this->failureMessage,
                providerResponse: ['error' => $this->failureMessage],
            );
        }

        $nfseNumber = 'FAKE-'.str_pad((string) count($this->emissions), 6, '0', STR_PAD_LEFT);

        if ($this->authorizeImmediately) {
            return new NFSeResultDTO(
                success: true,
                providerRef: $request->referenceId,
                status: 'authorized',
                nfseNumber: $nfseNumber,
                verificationCode: 'FAKE-VERIFY-'.bin2hex(random_bytes(4)),
                pdfUrl: "https://fake.focusnfe.com.br/nfse/{$request->referenceId}.pdf",
                xmlContent: '<NFSe><fake>true</fake></NFSe>',
                providerResponse: [
                    'status' => 'autorizada',
                    'numero' => $nfseNumber,
                ],
            );
        }

        return new NFSeResultDTO(
            success: true,
            providerRef: $request->referenceId,
            status: 'processing',
            providerResponse: ['status' => 'processando_autorizacao'],
        );
    }

    public function cancel(string $providerRef, string $reason): NFSeResultDTO
    {
        $this->cancellations[] = ['ref' => $providerRef, 'reason' => $reason];

        if (! $this->shouldSucceed) {
            return new NFSeResultDTO(
                success: false,
                providerRef: $providerRef,
                errorMessage: $this->failureMessage,
            );
        }

        return new NFSeResultDTO(
            success: true,
            providerRef: $providerRef,
            status: 'cancelled',
            providerResponse: ['status' => 'cancelada'],
        );
    }

    public function getStatus(string $providerRef): NFSeResultDTO
    {
        return new NFSeResultDTO(
            success: true,
            providerRef: $providerRef,
            status: 'authorized',
            nfseNumber: 'FAKE-000001',
            verificationCode: 'FAKE-VERIFY-001',
            pdfUrl: "https://fake.focusnfe.com.br/nfse/{$providerRef}.pdf",
            providerResponse: ['status' => 'autorizada'],
        );
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        return $signature === 'valid_signature';
    }

    // Test helpers

    public function setShouldSucceed(bool $shouldSucceed): void
    {
        $this->shouldSucceed = $shouldSucceed;
    }

    public function setFailureMessage(string $message): void
    {
        $this->failureMessage = $message;
    }

    public function setAuthorizeImmediately(bool $authorizeImmediately): void
    {
        $this->authorizeImmediately = $authorizeImmediately;
    }

    /**
     * @return array<NFSeRequestDTO>
     */
    public function getEmissions(): array
    {
        return $this->emissions;
    }

    /**
     * @return array<array{ref: string, reason: string}>
     */
    public function getCancellations(): array
    {
        return $this->cancellations;
    }

    public function reset(): void
    {
        $this->shouldSucceed = true;
        $this->failureMessage = 'Fiscal emission failed';
        $this->authorizeImmediately = true;
        $this->emissions = [];
        $this->cancellations = [];
    }
}
