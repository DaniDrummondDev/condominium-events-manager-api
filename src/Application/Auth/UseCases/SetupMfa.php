<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\TotpServiceInterface;
use Application\Auth\DTOs\MfaSetupDTO;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\RecoveryCodes;
use Domain\Auth\ValueObjects\TokenClaims;

final readonly class SetupMfa
{
    public function __construct(
        private PlatformUserRepositoryInterface $userRepository,
        private TotpServiceInterface $totpService,
    ) {}

    public function execute(TokenClaims $claims): MfaSetupDTO
    {
        $user = $this->userRepository->findById($claims->sub);

        if ($user === null) {
            throw AuthenticationException::invalidToken();
        }

        if ($user->hasMfaConfigured()) {
            throw AuthenticationException::invalidToken('MFA is already configured');
        }

        $secret = $this->totpService->generateSecret();
        $otpauthUri = $this->totpService->generateQrCodeUri(
            $secret,
            $user->email(),
            TokenClaims::ISSUER,
        );

        $recoveryCodes = RecoveryCodes::generate();

        return new MfaSetupDTO(
            secret: $secret,
            otpauthUri: $otpauthUri,
            recoveryCodes: $recoveryCodes,
        );
    }
}
