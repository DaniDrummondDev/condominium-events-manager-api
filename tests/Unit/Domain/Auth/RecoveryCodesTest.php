<?php

declare(strict_types=1);

use Domain\Auth\ValueObjects\RecoveryCodes;

it('generates exactly 8 codes', function () {
    $codes = RecoveryCodes::generate();

    expect($codes)->toHaveCount(8);
});

it('generates codes of exactly 10 characters', function () {
    $codes = RecoveryCodes::generate();

    foreach ($codes as $code) {
        expect(strlen($code))->toBe(10);
    }
});

it('generates hexadecimal codes', function () {
    $codes = RecoveryCodes::generate();

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[a-f0-9]{10}$/');
    }
});

it('generates unique codes', function () {
    $codes = RecoveryCodes::generate();

    expect(array_unique($codes))->toHaveCount(count($codes));
});

it('stores codes via constructor', function () {
    $recoveryCodes = new RecoveryCodes(['code1', 'code2']);

    expect($recoveryCodes->codes())->toBe(['code1', 'code2']);
});
