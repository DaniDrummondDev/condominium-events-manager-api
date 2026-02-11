<?php

declare(strict_types=1);

use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceRule;

// --- Factory method ---

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $spaceId = Uuid::generate();

    $rule = SpaceRule::create($id, $spaceId, 'max_guests', '30', 'Máximo de convidados permitidos');

    expect($rule->id())->toBe($id)
        ->and($rule->spaceId())->toBe($spaceId)
        ->and($rule->ruleKey())->toBe('max_guests')
        ->and($rule->ruleValue())->toBe('30')
        ->and($rule->description())->toBe('Máximo de convidados permitidos');
});

test('create() with null description', function () {
    $rule = SpaceRule::create(
        Uuid::generate(),
        Uuid::generate(),
        'noise_limit_db',
        '80',
        null,
    );

    expect($rule->description())->toBeNull();
});

// --- Property updates ---

test('updateValue() changes the ruleValue', function () {
    $rule = SpaceRule::create(Uuid::generate(), Uuid::generate(), 'max_guests', '30', 'Limite de convidados');

    $rule->updateValue('50');

    expect($rule->ruleValue())->toBe('50');
});

test('updateDescription() changes the description', function () {
    $rule = SpaceRule::create(Uuid::generate(), Uuid::generate(), 'max_guests', '30', 'Descrição original');

    $rule->updateDescription('Descrição atualizada');

    expect($rule->description())->toBe('Descrição atualizada');
});

test('updateDescription() accepts null', function () {
    $rule = SpaceRule::create(Uuid::generate(), Uuid::generate(), 'max_guests', '30', 'Descrição original');

    $rule->updateDescription(null);

    expect($rule->description())->toBeNull();
});
