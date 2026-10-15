<?php

declare(strict_types=1);

namespace App\Agent\Mcp;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Service with {@see Tool} methods for AI agents.
 */
#[AutoconfigureTag('app.agent_tools')]
interface ToolProvider
{
}
