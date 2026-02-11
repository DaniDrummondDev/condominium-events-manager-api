<?php

declare(strict_types=1);

namespace Domain\Auth\ValueObjects;

final readonly class RecoveryCodes
{
    private const int CODE_COUNT = 8;

    private const int CODE_LENGTH = 10;

    /**
     * @param  array<string>  $codes
     */
    public function __construct(
        private array $codes,
    ) {}

    /**
     * @return array<string>
     */
    public static function generate(): array
    {
        $codes = [];
        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $codes[] = bin2hex(random_bytes((int) ceil(self::CODE_LENGTH / 2)));
        }

        return array_map(fn (string $code) => substr($code, 0, self::CODE_LENGTH), $codes);
    }

    /**
     * @return array<string>
     */
    public function codes(): array
    {
        return $this->codes;
    }
}
