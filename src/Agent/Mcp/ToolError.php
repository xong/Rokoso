<?php

declare(strict_types=1);

namespace App\Agent\Mcp;

/**
 * Error the agent should see and can react to (invalid argument, not found, not allowed).
 */
final class ToolError extends \RuntimeException
{
}
