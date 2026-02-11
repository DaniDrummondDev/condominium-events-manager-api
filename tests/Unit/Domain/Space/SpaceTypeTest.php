<?php

declare(strict_types=1);

use Domain\Space\Enums\SpaceType;

// --- Enum cases ---

test('SpaceType has exactly 8 cases', function () {
    expect(SpaceType::cases())->toHaveCount(8);
});

test('SpaceType has correct enum values', function () {
    expect(SpaceType::PartyHall->value)->toBe('party_hall')
        ->and(SpaceType::Bbq->value)->toBe('bbq')
        ->and(SpaceType::Pool->value)->toBe('pool')
        ->and(SpaceType::Gym->value)->toBe('gym')
        ->and(SpaceType::Playground->value)->toBe('playground')
        ->and(SpaceType::SportsCourt->value)->toBe('sports_court')
        ->and(SpaceType::MeetingRoom->value)->toBe('meeting_room')
        ->and(SpaceType::Other->value)->toBe('other');
});

// --- Labels ---

test('PartyHall label is Salão de Festas', function () {
    expect(SpaceType::PartyHall->label())->toBe('Salão de Festas');
});

test('Bbq label is Churrasqueira', function () {
    expect(SpaceType::Bbq->label())->toBe('Churrasqueira');
});

test('Pool label is Piscina', function () {
    expect(SpaceType::Pool->label())->toBe('Piscina');
});

test('Gym label is Academia', function () {
    expect(SpaceType::Gym->label())->toBe('Academia');
});

test('Playground label is Playground', function () {
    expect(SpaceType::Playground->label())->toBe('Playground');
});

test('SportsCourt label is Quadra Esportiva', function () {
    expect(SpaceType::SportsCourt->label())->toBe('Quadra Esportiva');
});

test('MeetingRoom label is Sala de Reunião', function () {
    expect(SpaceType::MeetingRoom->label())->toBe('Sala de Reunião');
});

test('Other label is Outro', function () {
    expect(SpaceType::Other->label())->toBe('Outro');
});
