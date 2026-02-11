<?php

declare(strict_types=1);

use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\Exceptions\DomainException;

// --- Constructor and generate ---

test('creates invoice number with valid year and sequence', function () {
    $number = new InvoiceNumber(2025, 1);

    expect($number->year())->toBe(2025)
        ->and($number->sequence())->toBe(1);
});

test('generate creates invoice number via static factory', function () {
    $number = InvoiceNumber::generate(2025, 42);

    expect($number->year())->toBe(2025)
        ->and($number->sequence())->toBe(42);
});

// --- Validation ---

test('throws on year below 2020', function () {
    new InvoiceNumber(2019, 1);
})->throws(DomainException::class, 'Invoice year must be between 2020 and 2099');

test('throws on year above 2099', function () {
    new InvoiceNumber(2100, 1);
})->throws(DomainException::class, 'Invoice year must be between 2020 and 2099');

test('throws on zero sequence', function () {
    new InvoiceNumber(2025, 0);
})->throws(DomainException::class, 'Invoice sequence must be positive');

test('throws on negative sequence', function () {
    new InvoiceNumber(2025, -1);
})->throws(DomainException::class, 'Invoice sequence must be positive');

test('accepts boundary years 2020 and 2099', function () {
    $low = new InvoiceNumber(2020, 1);
    $high = new InvoiceNumber(2099, 1);

    expect($low->year())->toBe(2020)
        ->and($high->year())->toBe(2099);
});

// --- value and toString ---

test('value formats with zero-padded year and sequence', function () {
    $number = new InvoiceNumber(2025, 1);

    expect($number->value())->toBe('INV-2025-0001');
});

test('value formats large sequence numbers correctly', function () {
    $number = new InvoiceNumber(2025, 12345);

    expect($number->value())->toBe('INV-2025-12345');
});

test('toString returns same as value', function () {
    $number = new InvoiceNumber(2025, 7);

    expect((string) $number)->toBe('INV-2025-0007');
});

// --- fromString ---

test('fromString parses valid invoice number', function () {
    $number = InvoiceNumber::fromString('INV-2025-0042');

    expect($number->year())->toBe(2025)
        ->and($number->sequence())->toBe(42);
});

test('fromString parses large sequence numbers', function () {
    $number = InvoiceNumber::fromString('INV-2025-12345');

    expect($number->year())->toBe(2025)
        ->and($number->sequence())->toBe(12345);
});

test('fromString throws on invalid format without prefix', function () {
    InvoiceNumber::fromString('2025-0001');
})->throws(DomainException::class, 'Invalid invoice number format');

test('fromString throws on invalid format with wrong prefix', function () {
    InvoiceNumber::fromString('REC-2025-0001');
})->throws(DomainException::class, 'Invalid invoice number format');

test('fromString throws on empty string', function () {
    InvoiceNumber::fromString('');
})->throws(DomainException::class, 'Invalid invoice number format');

test('fromString throws on sequence with less than 4 digits', function () {
    InvoiceNumber::fromString('INV-2025-001');
})->throws(DomainException::class, 'Invalid invoice number format');

test('fromString roundtrips correctly', function () {
    $original = InvoiceNumber::generate(2026, 99);
    $parsed = InvoiceNumber::fromString($original->value());

    expect($parsed->year())->toBe($original->year())
        ->and($parsed->sequence())->toBe($original->sequence())
        ->and($parsed->value())->toBe($original->value());
});
