<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentScope;
use App\Agent\Mcp\Tool;
use App\Agent\Mcp\ToolArguments;
use App\Agent\Mcp\ToolProvider;
use App\Entity\CalendarItem;
use App\Entity\Contact;
use App\Entity\Facility;
use App\Entity\ForumTopic;
use App\Entity\Message;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Entity\WikiPage;
use App\Enum\Feature;
use App\Help\HelpArticle;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use App\Search\GlobalSearch;

/**
 * Who am I, organizations, projects and the search across all areas.
 */
final readonly class GeneralTools implements ToolProvider
{
    /** search groups and the permission they need */
    private const array SEARCH_SCOPES = [
        'messages' => AgentScope::MailRead,
        'contacts' => AgentScope::ContactsRead,
        'facilities' => AgentScope::ContactsRead,
        'events' => AgentScope::CalendarRead,
        'topics' => AgentScope::KnowledgeRead,
        'wiki' => AgentScope::KnowledgeRead,
        'files' => AgentScope::KnowledgeRead,
        'help' => null,
    ];

    public function __construct(
        private ToolKit $kit,
        private AgentContext $agent,
        private OrganizationRepository $organizations,
        private ProjectRepository $projects,
        private GlobalSearch $search,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('whoami', 'The person you act for, this connection and its permissions.')]
    public function whoami(ToolArguments $args, User $user): array
    {
        $token = $this->agent->current();

        return [
            'person' => ['id' => $user->getId(), 'name' => $user->getName(), 'email' => $user->getEmail()],
            'connection' => $this->agent->name(),
            'permissions' => array_map(static fn (AgentScope $s): string => $s->value, $token?->getScopes() ?? []),
            'organizations' => $this->organizationList($user),
            'now' => ToolKit::time(new \DateTimeImmutable()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_organizations', 'Organizations of the person that allow AI agents, with their areas (features) and the role of the person.')]
    public function listOrganizations(ToolArguments $args, User $user): array
    {
        return ['organizations' => $this->organizationList($user)];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_projects', 'Active projects the person can see (used to file e-mails, tasks, events and forum topics).')]
    public function listProjects(ToolArguments $args, User $user): array
    {
        $projects = [];
        foreach ($this->projects->findVisibleFor($user) as $project) {
            $projects[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'organization' => ToolKit::org($project->getOrganization()),
                'description' => ToolKit::excerpt($project->getDescription(), 300),
                'url' => $this->kit->url('project_show', ['id' => $project->getId()]),
            ];
        }

        return ['projects' => $projects];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('search', 'Searches all areas this connection may read (e-mails, contacts, facilities, events and tasks, forum, handbook, files, help). Words are combined with AND; use quotes for phrases.',
        properties: ['query' => ['type' => 'string', 'description' => 'Search words, at least 2 characters']],
        required: ['query'])]
    public function search(ToolArguments $args, User $user): array
    {
        $token = $this->agent->current();
        $results = [];
        foreach ($this->search->search($user, $args->requiredString('query')) as $group => $items) {
            if (!\array_key_exists($group, self::SEARCH_SCOPES)) {
                continue;
            }
            $scope = self::SEARCH_SCOPES[$group];
            if (null !== $scope && !($token?->allows($scope) ?? false)) {
                continue;
            }
            foreach ($items as $item) {
                $entry = $this->describe($item);
                if (null !== $entry) {
                    $results[] = ['type' => $group] + $entry;
                }
            }
        }

        return ['results' => $results];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function organizationList(User $user): array
    {
        $list = [];
        foreach (array_filter($this->organizations->findForUser($user), $this->agent->allows(...)) as $organization) {
            $list[] = [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'admin' => $organization->isAdmin($user),
                'features' => array_values(array_map(static fn (Feature $f): string => $f->value, array_filter(Feature::cases(), $organization->hasFeature(...)))),
            ];
        }

        return $list;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function describe(object $item): ?array
    {
        return match (true) {
            $item instanceof Message => ['id' => $item->getId(), 'title' => $item->getSubject(), 'from' => $item->getFromLabel(), 'date' => ToolKit::time($item->getDate()),
                'url' => $this->kit->url('mail_show', ['folder' => 'all', 'id' => $item->getId()])],
            $item instanceof Contact => ['id' => $item->getId(), 'title' => $item->getDisplayName(), 'email' => $item->getEmail(), 'url' => $this->kit->url('contact_show', ['id' => $item->getId()])],
            $item instanceof Facility => ['id' => $item->getId(), 'title' => $item->getName(), 'url' => $this->kit->url('facility_show', ['id' => $item->getId()])],
            $item instanceof CalendarItem => ['id' => $item->getId(), 'title' => $item->getTitle(), 'kind' => $item->getType()->value, 'date' => ToolKit::time($item->getStartsAt()),
                'url' => $this->kit->url('calendar_item_show', ['id' => $item->getId()])],
            $item instanceof ForumTopic => ['id' => $item->getId(), 'title' => $item->getTitle(), 'url' => $this->kit->url('forum_topic_show', ['id' => $item->getId()])],
            $item instanceof WikiPage => ['id' => $item->getId(), 'title' => $item->getTitle(), 'url' => $this->kit->url('wiki_show', ['id' => $item->getId()])],
            $item instanceof StoredFile => ['id' => $item->getId(), 'title' => $item->getFilename(), 'url' => $this->kit->url('file_show', ['id' => $item->getId()])],
            $item instanceof HelpArticle => ['id' => $item->slug, 'title' => $item->title, 'summary' => $item->summary, 'url' => $this->kit->url('help_show', ['slug' => $item->slug])],
            default => null,
        };
    }
}
