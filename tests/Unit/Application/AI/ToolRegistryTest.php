<?php

declare(strict_types=1);

use Application\AI\ToolRegistry;

afterEach(fn () => Mockery::close());

test('registers and retrieves tool by name', function () {
    $registry = new ToolRegistry();

    $registry->register(
        name: 'test_tool',
        description: 'A test tool',
        parameters: [
            ['name' => 'query', 'type' => 'string', 'description' => 'Search query', 'required' => true],
        ],
        handler: fn (string $query) => "result: {$query}",
    );

    $tool = $registry->getToolByName('test_tool');

    expect($tool)->not->toBeNull()
        ->and($tool['name'])->toBe('test_tool')
        ->and($tool['description'])->toBe('A test tool')
        ->and($tool['requiresConfirmation'])->toBeFalse();
});

test('returns null for unknown tool', function () {
    $registry = new ToolRegistry();

    expect($registry->getToolByName('nonexistent'))->toBeNull();
});

test('returns Prism tools array', function () {
    $registry = new ToolRegistry();

    $registry->register(
        name: 'read_tool',
        description: 'Reads data',
        parameters: [],
        handler: fn () => 'data',
    );

    $prismTools = $registry->getToolsForPrism();

    expect($prismTools)->toHaveCount(1)
        ->and($prismTools[0])->toBeInstanceOf(\Prism\Prism\Tool::class)
        ->and($prismTools[0]->name())->toBe('read_tool');
});

test('identifies tools requiring confirmation', function () {
    $registry = new ToolRegistry();

    $registry->register(
        name: 'mutation_tool',
        description: 'Mutates data',
        parameters: [],
        handler: fn () => 'done',
        requiresConfirmation: true,
    );

    expect($registry->requiresConfirmation('mutation_tool'))->toBeTrue()
        ->and($registry->requiresConfirmation('unknown'))->toBeFalse();
});

test('returns registered tool names', function () {
    $registry = new ToolRegistry();

    $registry->register('tool_a', 'A', [], fn () => '', false);
    $registry->register('tool_b', 'B', [], fn () => '', true);

    expect($registry->getRegisteredToolNames())->toBe(['tool_a', 'tool_b']);
});
