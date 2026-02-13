<?php

declare(strict_types=1);

namespace Application\AI;

use Prism\Prism\Tool;

final class ToolRegistry
{
    /** @var array<string, array{name: string, description: string, parameters: array<array{name: string, type: string, description: string, required: bool}>, handler: callable, requiresConfirmation: bool}> */
    private array $tools = [];

    /**
     * @param array<array{name: string, type: string, description: string, required: bool}> $parameters
     */
    public function register(
        string $name,
        string $description,
        array $parameters,
        callable $handler,
        bool $requiresConfirmation = false,
    ): void {
        $this->tools[$name] = [
            'name' => $name,
            'description' => $description,
            'parameters' => $parameters,
            'handler' => $handler,
            'requiresConfirmation' => $requiresConfirmation,
        ];
    }

    /**
     * @return array<string, array{name: string, description: string, parameters: array<array{name: string, type: string, description: string, required: bool}>, handler: callable, requiresConfirmation: bool}>|null
     */
    public function getToolByName(string $name): ?array
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<Tool>
     */
    public function getToolsForPrism(): array
    {
        $prismTools = [];

        foreach ($this->tools as $definition) {
            $tool = (new Tool())
                ->as($definition['name'])
                ->for($definition['description']);

            foreach ($definition['parameters'] as $param) {
                match ($param['type']) {
                    'string' => $tool->withStringParameter($param['name'], $param['description'], $param['required']),
                    'number' => $tool->withNumberParameter($param['name'], $param['description'], $param['required']),
                    'boolean' => $tool->withBooleanParameter($param['name'], $param['description'], $param['required']),
                    default => $tool->withStringParameter($param['name'], $param['description'], $param['required']),
                };
            }

            // Read-only tools get their handler wired directly
            if (! $definition['requiresConfirmation']) {
                $tool->using($definition['handler']);
            }

            $prismTools[] = $tool;
        }

        return $prismTools;
    }

    public function requiresConfirmation(string $toolName): bool
    {
        $tool = $this->tools[$toolName] ?? null;

        return $tool !== null && $tool['requiresConfirmation'];
    }

    /**
     * @return array<string>
     */
    public function getRegisteredToolNames(): array
    {
        return array_keys($this->tools);
    }
}
