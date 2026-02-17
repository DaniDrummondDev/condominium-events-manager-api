<?php

declare(strict_types=1);

use Domain\Billing\ValueObjects\Cnpj;
use Domain\Shared\Exceptions\DomainException;

// --- Valid CNPJs ---

test('creates valid CNPJ from digits only', function () {
    $cnpj = new Cnpj('11222333000181');

    expect($cnpj->value())->toBe('11222333000181');
});

test('creates valid CNPJ from formatted string', function () {
    $cnpj = new Cnpj('11.222.333/0001-81');

    expect($cnpj->value())->toBe('11222333000181');
});

test('creates valid CNPJ via fromString factory', function () {
    $cnpj = Cnpj::fromString('11222333000181');

    expect($cnpj->value())->toBe('11222333000181');
});

// --- Formatting ---

test('formats CNPJ for display', function () {
    $cnpj = new Cnpj('11222333000181');

    expect($cnpj->formatted())->toBe('11.222.333/0001-81');
});

test('toString returns digits only', function () {
    $cnpj = new Cnpj('11222333000181');

    expect((string) $cnpj)->toBe('11222333000181');
});

// --- Equality ---

test('equals returns true for same CNPJ', function () {
    $cnpj1 = new Cnpj('11222333000181');
    $cnpj2 = new Cnpj('11.222.333/0001-81');

    expect($cnpj1->equals($cnpj2))->toBeTrue();
});

test('equals returns false for different CNPJ', function () {
    $cnpj1 = new Cnpj('11222333000181');
    $cnpj2 = new Cnpj('11444777000161');

    expect($cnpj1->equals($cnpj2))->toBeFalse();
});

// --- Invalid CNPJs ---

test('rejects CNPJ with wrong length', function () {
    new Cnpj('1234567890');
})->throws(DomainException::class, 'CNPJ must have exactly 14 digits');

test('rejects empty CNPJ', function () {
    new Cnpj('');
})->throws(DomainException::class, 'CNPJ must have exactly 14 digits');

test('rejects CNPJ with all identical digits', function () {
    new Cnpj('11111111111111');
})->throws(DomainException::class, 'CNPJ with all identical digits is invalid');

test('rejects CNPJ with all zeros', function () {
    new Cnpj('00000000000000');
})->throws(DomainException::class, 'CNPJ with all identical digits is invalid');

test('rejects CNPJ with invalid check digits', function () {
    new Cnpj('11222333000199');
})->throws(DomainException::class, 'CNPJ check digits are invalid');

test('rejects CNPJ with wrong first check digit', function () {
    new Cnpj('11222333000191');
})->throws(DomainException::class, 'CNPJ check digits are invalid');

// --- Known valid CNPJs ---

test('accepts known valid CNPJs', function (string $cnpj) {
    $result = new Cnpj($cnpj);

    expect($result->value())->toHaveLength(14);
})->with([
    '11222333000181',
    '11444777000161',
    '00000000000191', // Receita Federal test CNPJ
]);
