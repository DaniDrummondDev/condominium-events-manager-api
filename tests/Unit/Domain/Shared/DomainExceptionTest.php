<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\DomainException;

test('creates exception with error code and context', function () {
    $exception = new DomainException(
        'Something went wrong',
        'ERR_001',
        ['key' => 'value'],
    );

    expect($exception->getMessage())->toBe('Something went wrong')
        ->and($exception->errorCode())->toBe('ERR_001')
        ->and($exception->context())->toBe(['key' => 'value']);
});

test('creates exception with empty context', function () {
    $exception = new DomainException('Oops', 'ERR_002');

    expect($exception->context())->toBe([]);
});

test('is instance of RuntimeException', function () {
    $exception = new DomainException('Test', 'ERR');

    expect($exception)->toBeInstanceOf(RuntimeException::class);
});
