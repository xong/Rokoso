<?php

declare(strict_types=1);

namespace App\Agent\Mcp;

use App\Entity\AgentToken;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * All {@see Tool} methods of the tagged {@see ToolProvider} services.
 */
final class ToolRegistry
{
    /** @var array<string, array{tool: Tool, call: \Closure}>|null */
    private ?array $tools = null;

    /**
     * @param iterable<ToolProvider> $providers
     */
    public function __construct(#[AutowireIterator('app.agent_tools')] private readonly iterable $providers)
    {
    }

    /**
     * @return array<string, array{tool: Tool, call: \Closure}>
     */
    public function all(): array
    {
        if (null === $this->tools) {
            $this->tools = [];
            foreach ($this->providers as $provider) {
                foreach ((new \ReflectionObject($provider))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    foreach ($method->getAttributes(Tool::class) as $attribute) {
                        $tool = $attribute->newInstance();
                        $this->tools[$tool->name] = ['tool' => $tool, 'call' => $method->getClosure($provider)];
                    }
                }
            }
            ksort($this->tools);
        }

        return $this->tools;
    }

    /**
     * Tools the token may use (scope).
     *
     * @return list<Tool>
     */
    public function availableFor(AgentToken $token): array
    {
        return array_values(array_filter(
            array_map(static fn (array $t): Tool => $t['tool'], $this->all()),
            static fn (Tool $tool): bool => null === $tool->scope || $token->allows($tool->scope),
        ));
    }

    /**
     * @return array{tool: Tool, call: \Closure}|null
     */
    public function get(string $name): ?array
    {
        return $this->all()[$name] ?? null;
    }
}
