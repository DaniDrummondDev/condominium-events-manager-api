<?php

declare(strict_types=1);

use Domain\Governance\Entities\CondominiumRule;
use Domain\Shared\ValueObjects\Uuid;

function createCondominiumRule(
    ?Uuid $id = null,
    string $title = 'Regra de Silencio',
    string $description = 'Silencio apos as 22h',
    string $category = 'convivencia',
    int $order = 1,
    ?Uuid $createdBy = null,
): CondominiumRule {
    return CondominiumRule::create(
        id: $id ?? Uuid::generate(),
        title: $title,
        description: $description,
        category: $category,
        order: $order,
        createdBy: $createdBy ?? Uuid::generate(),
    );
}

// -- create() ----------------------------------------------------------------

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $createdBy = Uuid::generate();

    $rule = CondominiumRule::create(
        id: $id,
        title: 'Regra de Silencio',
        description: 'Silencio apos as 22h',
        category: 'convivencia',
        order: 3,
        createdBy: $createdBy,
    );

    expect($rule->id())->toBe($id)
        ->and($rule->title())->toBe('Regra de Silencio')
        ->and($rule->description())->toBe('Silencio apos as 22h')
        ->and($rule->category())->toBe('convivencia')
        ->and($rule->order())->toBe(3)
        ->and($rule->createdBy())->toBe($createdBy)
        ->and($rule->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() defaults isActive to true', function () {
    $rule = createCondominiumRule();

    expect($rule->isActive())->toBeTrue();
});

// -- updateTitle() -----------------------------------------------------------

test('updateTitle() changes title', function () {
    $rule = createCondominiumRule(title: 'Titulo Original');

    $rule->updateTitle('Titulo Atualizado');

    expect($rule->title())->toBe('Titulo Atualizado');
});

// -- updateDescription() -----------------------------------------------------

test('updateDescription() changes description', function () {
    $rule = createCondominiumRule(description: 'Descricao original');

    $rule->updateDescription('Descricao atualizada');

    expect($rule->description())->toBe('Descricao atualizada');
});

// -- updateCategory() --------------------------------------------------------

test('updateCategory() changes category', function () {
    $rule = createCondominiumRule(category: 'convivencia');

    $rule->updateCategory('seguranca');

    expect($rule->category())->toBe('seguranca');
});

// -- updateOrder() -----------------------------------------------------------

test('updateOrder() changes order', function () {
    $rule = createCondominiumRule(order: 1);

    $rule->updateOrder(5);

    expect($rule->order())->toBe(5);
});

// -- activate() / deactivate() -----------------------------------------------

test('activate() sets isActive to true', function () {
    $rule = createCondominiumRule();
    $rule->deactivate();

    $rule->activate();

    expect($rule->isActive())->toBeTrue();
});

test('deactivate() sets isActive to false', function () {
    $rule = createCondominiumRule();

    $rule->deactivate();

    expect($rule->isActive())->toBeFalse();
});
