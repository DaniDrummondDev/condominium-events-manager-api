<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\TotpServiceInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Auth\Events\MfaEnabled;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;

final readonly class ConfirmMfaSetup
{
    public function __construct(
        private PlatformUserRepositoryInterface $userRepository,
        private TotpServiceInterface $totpService,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(TokenClaims $claims, string $secret, string $code): void
    {
        $user = $this->userRepository->findById($claims->sub);

        if ($user === null) {
            throw AuthenticationException::invalidToken();
        }

        if (! $this->totpService->verify($secret, $code)) {
            throw AuthenticationException::invalidToken('Invalid TOTP code');
        }

        $user->enableMfa($secret);
        $this->userRepository->save($user);

        $this->eventDispatcher->dispatch(new MfaEnabled(userId: $user->id()));
    }
}
