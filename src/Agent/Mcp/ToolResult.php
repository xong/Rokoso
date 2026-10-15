<?php

declare(strict_types=1);

namespace App\Agent\Mcp;

/**
 * Tool result with additional MCP content blocks (e.g. an image or a file), besides the structured data.
 */
final readonly class ToolResult
{
    /**
     * @param array<string, mixed>       $data
     * @param list<array<string, mixed>> $content extra blocks after the JSON text, e.g. ['type' => 'image', …]
     */
    public function __construct(public array $data, public array $content = [])
    {
    }
}
