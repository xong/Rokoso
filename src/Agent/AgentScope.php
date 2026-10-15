<?php

declare(strict_types=1);

namespace App\Agent;

/**
 * What an AI agent may do with a token. Writing includes reading the same area.
 */
enum AgentScope: string
{
    case MailRead = 'mail:read';
    case MailWrite = 'mail:write';
    case ContactsRead = 'contacts:read';
    case ContactsWrite = 'contacts:write';
    case CalendarRead = 'calendar:read';
    case CalendarWrite = 'calendar:write';
    case KnowledgeRead = 'knowledge:read';
    case KnowledgeWrite = 'knowledge:write';

    public function label(): string
    {
        return 'agent.scope.'.str_replace(':', '_', $this->value);
    }

    public function isWrite(): bool
    {
        return str_ends_with($this->value, ':write');
    }

    /**
     * Scope that includes this one: the write scope of the same area (or itself).
     */
    public function writeScope(): self
    {
        return self::from(str_replace(':read', ':write', $this->value));
    }

    /**
     * Space separated scope string (OAuth); unknown values are ignored, empty means all.
     *
     * @return list<self>
     */
    public static function parse(?string $scopes): array
    {
        $parsed = [];
        foreach (preg_split('/\s+/', trim((string) $scopes)) ?: [] as $value) {
            $scope = self::tryFrom($value);
            if (null !== $scope && !\in_array($scope, $parsed, true)) {
                $parsed[] = $scope;
            }
        }

        return [] === $parsed ? self::cases() : $parsed;
    }

    /**
     * @param list<self> $scopes
     */
    public static function join(array $scopes): string
    {
        return implode(' ', array_map(static fn (self $s): string => $s->value, $scopes));
    }
}
