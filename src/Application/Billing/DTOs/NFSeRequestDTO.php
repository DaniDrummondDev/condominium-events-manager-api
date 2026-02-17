<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class NFSeRequestDTO
{
    /**
     * @param  array<string, mixed>  $emitter  Dados do prestador (plataforma SaaS)
     * @param  array<string, mixed>  $recipient  Dados do tomador (condomínio)
     */
    public function __construct(
        public string $referenceId,
        public string $serviceDescription,
        public string $competenceDate,
        public int $totalAmountInCents,
        public float $issRate,
        public int $issAmountInCents,
        public array $emitter,
        public array $recipient,
        public array $metadata = [],
    ) {}
}
