<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;

test('creates money with default BRL currency', function () {
    $money = new Money(1500);

    expect($money->amount())->toBe(1500)
        ->and($money->currency())->toBe('BRL');
});

test('creates money with explicit currency', function () {
    $money = new Money(1000, 'USD');

    expect($money->amount())->toBe(1000)
        ->and($money->currency())->toBe('USD');
});

test('throws on negative amount', function () {
    new Money(-100);
})->throws(DomainException::class, 'Money amount cannot be negative');

test('throws on invalid currency code', function () {
    new Money(100, 'BRLX');
})->throws(DomainException::class, 'Currency must be a 3-letter ISO 4217 code');

test('adds two money values', function () {
    $a = new Money(1000, 'BRL');
    $b = new Money(500, 'BRL');
    $result = $a->add($b);

    expect($result->amount())->toBe(1500)
        ->and($result->currency())->toBe('BRL');
});

test('throws when adding different currencies', function () {
    $brl = new Money(1000, 'BRL');
    $usd = new Money(500, 'USD');

    $brl->add($usd);
})->throws(DomainException::class, 'Cannot operate on different currencies');

test('subtracts two money values', function () {
    $a = new Money(1500, 'BRL');
    $b = new Money(500, 'BRL');
    $result = $a->subtract($b);

    expect($result->amount())->toBe(1000);
});

test('throws when subtraction would be negative', function () {
    $a = new Money(500);
    $b = new Money(1000);

    $a->subtract($b);
})->throws(DomainException::class, 'Cannot subtract: result would be negative');

test('multiplies money by factor', function () {
    $money = new Money(500);
    $result = $money->multiply(3);

    expect($result->amount())->toBe(1500);
});

test('checks if money is zero', function () {
    expect((new Money(0))->isZero())->toBeTrue()
        ->and((new Money(1))->isZero())->toBeFalse();
});

test('equals compares amount and currency', function () {
    $a = new Money(1000, 'BRL');
    $b = new Money(1000, 'BRL');
    $c = new Money(1000, 'USD');
    $d = new Money(500, 'BRL');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and($a->equals($d))->toBeFalse();
});

test('greaterThan compares amounts', function () {
    $a = new Money(1500);
    $b = new Money(1000);

    expect($a->greaterThan($b))->toBeTrue()
        ->and($b->greaterThan($a))->toBeFalse();
});

test('converts to string with BRL formatting', function () {
    $money = new Money(1550, 'BRL');

    expect((string) $money)->toBe('15,50 BRL');
});

test('zero money converts to string correctly', function () {
    expect((string) new Money(0))->toBe('0,00 BRL');
});
