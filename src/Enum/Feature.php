<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Areas an organization can switch off (organization settings). Switched-off areas are hidden, their data is kept.
 * "Today", notifications, the personal shelf, direct messages and the administration are always available.
 */
enum Feature: string
{
    case Mail = 'mail';
    case Forum = 'forum';
    case Calendar = 'calendar';
    case Tasks = 'tasks';
    case Meetings = 'meetings';
    case Resolutions = 'resolutions';
    case Polls = 'polls';
    case Surveys = 'surveys';
    case Projects = 'projects';
    case Files = 'files';
    case Wiki = 'wiki';
    case Contacts = 'contacts';
    case PublicPage = 'public_page';

    public function label(): string
    {
        return 'feature.'.$this->value;
    }

    public function help(): string
    {
        return 'feature.'.$this->value.'_help';
    }

    /**
     * Route name prefixes of the area; a page is only reachable while at least one organization of the user uses the area.
     *
     * @return list<string>
     */
    public function routePrefixes(): array
    {
        return match ($this) {
            // the mailbox itself stays (direct messages), e-mails of switched-off organizations are filtered out
            self::Mail => ['mail_compose', 'mail_account_', 'mail_rule_', 'mail_signatures', 'snippet_'],
            self::Forum => ['forum_'],
            // calendar_item_* also shows tasks: checked per item by the voter
            self::Calendar => ['calendar_month', 'calendar_week', 'calendar_day', 'calendar_occurrence_'],
            self::Tasks => ['task_'],
            self::Meetings => ['meeting_'],
            self::Resolutions => ['resolution_'],
            self::Polls => ['poll_'],
            self::Surveys => ['survey_'],
            self::Projects => ['project_'],
            self::Files => ['file_', 'folder_'],
            self::Wiki => ['wiki_'],
            self::Contacts => ['contact_'],
            self::PublicPage => ['public_settings', 'public_topic_'],
        };
    }

    /**
     * Area a route belongs to (longest matching prefix), null for areas that are always available.
     */
    public static function forRoute(string $route): ?self
    {
        $found = null;
        $length = 0;
        foreach (self::cases() as $feature) {
            foreach ($feature->routePrefixes() as $prefix) {
                if (\strlen($prefix) > $length && str_starts_with($route, $prefix)) {
                    $found = $feature;
                    $length = \strlen($prefix);
                }
            }
        }

        return $found;
    }
}
