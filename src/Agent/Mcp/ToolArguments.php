<?php

declare(strict_types=1);

namespace App\Agent\Mcp;

use App\Calendar\CalendarService;

/**
 * Typed access to the arguments of a tool call; invalid values end the call with a {@see ToolError}.
 */
final readonly class ToolArguments
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values)
    {
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values) && null !== $this->values[$name];
    }

    public function string(string $name, string $default = ''): string
    {
        $value = $this->values[$name] ?? $default;
        if (\is_int($value) || \is_float($value)) {
            $value = (string) $value;
        }
        if (!\is_string($value)) {
            throw new ToolError(\sprintf('Argument "%s" must be a string.', $name));
        }

        return trim($value);
    }

    public function requiredString(string $name): string
    {
        $value = $this->string($name);
        if ('' === $value) {
            throw new ToolError(\sprintf('Argument "%s" is required.', $name));
        }

        return $value;
    }

    public function optionalString(string $name): ?string
    {
        return $this->has($name) ? $this->string($name) : null;
    }

    public function int(string $name, ?int $default = null): ?int
    {
        $value = $this->values[$name] ?? null;
        if (null === $value) {
            return $default;
        }
        if (\is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        if (!\is_int($value)) {
            throw new ToolError(\sprintf('Argument "%s" must be an integer.', $name));
        }

        return $value;
    }

    public function requiredInt(string $name): int
    {
        return $this->int($name) ?? throw new ToolError(\sprintf('Argument "%s" is required.', $name));
    }

    public function bool(string $name, bool $default = false): bool
    {
        $value = $this->values[$name] ?? $default;
        if (!\is_bool($value)) {
            throw new ToolError(\sprintf('Argument "%s" must be true or false.', $name));
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    public function ints(string $name): array
    {
        $value = $this->values[$name] ?? [];
        if (!\is_array($value)) {
            throw new ToolError(\sprintf('Argument "%s" must be a list of integers.', $name));
        }

        return array_values(array_map(static fn ($v): int => \is_int($v) || (\is_string($v) && ctype_digit($v)) ? (int) $v
            : throw new ToolError(\sprintf('Argument "%s" must be a list of integers.', $name)), $value));
    }

    /**
     * Date or date and time (ISO 8601); without time zone in Europe/Berlin.
     */
    public function date(string $name): ?\DateTimeImmutable
    {
        $value = $this->optionalString($name);
        if (null === $value || '' === $value) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value, new \DateTimeZone(CalendarService::TIMEZONE));
        } catch (\Exception) {
            throw new ToolError(\sprintf('Argument "%s" must be a date like 2026-10-31 or 2026-10-31T18:00.', $name));
        }
    }

    /**
     * @param list<string> $allowed
     */
    public function choice(string $name, array $allowed, ?string $default = null): ?string
    {
        $value = $this->optionalString($name) ?? $default;
        if (null !== $value && !\in_array($value, $allowed, true)) {
            throw new ToolError(\sprintf('Argument "%s" must be one of: %s.', $name, implode(', ', $allowed)));
        }

        return $value;
    }
}
