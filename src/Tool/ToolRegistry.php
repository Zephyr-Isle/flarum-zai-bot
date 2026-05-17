<?php

namespace Zephyrisle\ZaiBot\Tool;

use Flarum\Extension\ExtensionManager;

class ToolRegistry
{
    private array $tools = [];

    public function __construct(private ExtensionManager $extensions)
    {
    }

    public function register(string $name, array $definition): void
    {
        $this->tools[$name] = $definition + ['name' => $name];
    }

    public function all(): array
    {
        return $this->tools;
    }

    public function isAvailable(string $name): bool
    {
        $tool = $this->tools[$name] ?? null;

        if ($tool === null) {
            return false;
        }

        $dependency = $tool['dependency'] ?? null;

        return $dependency === null || $this->extensions->isEnabled($dependency);
    }
}
