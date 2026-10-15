<?php

declare(strict_types=1);

namespace App\Agent\Mcp;

use App\Agent\AgentScope;

/**
 * A method of a {@see ToolProvider} offered to AI agents. The method receives {@see ToolArguments} and the user
 * and returns an associative array (the structured result).
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Tool
{
    /**
     * @param array<string, array<string, mixed>> $properties JSON schema of each argument
     * @param list<string>                        $required
     * @param AgentScope|null                     $scope      null = always available
     */
    public function __construct(
        public string $name,
        public string $description,
        public ?AgentScope $scope = null,
        public array $properties = [],
        public array $required = [],
        public bool $readOnly = true,
        public bool $destructive = false,
        public bool $idempotent = false,
    ) {
    }
}
