<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentScope;
use App\Agent\Mcp\Tool;
use App\Agent\Mcp\ToolArguments;
use App\Agent\Mcp\ToolError;
use App\Agent\Mcp\ToolProvider;
use App\Agent\Mcp\ToolResult;
use App\Entity\Folder;
use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\ForumTopicRead;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Entity\WikiPage;
use App\Enum\Feature;
use App\Notification\ActivityNotifier;
use App\Repository\FolderRepository;
use App\Repository\ForumBoardRepository;
use App\Repository\ForumTopicRepository;
use App\Repository\StoredFileRepository;
use App\Repository\WikiPageRepository;
use App\Security\Voter\FolderVoter;
use App\Security\Voter\ForumVoter;
use App\Security\Voter\WikiVoter;
use App\Service\FileStorage;

/**
 * Forum, handbook (wiki) and files: read and search; write topics, replies and handbook pages.
 */
final readonly class KnowledgeTools implements ToolProvider
{
    private const int MAX_TEXT = 50000;

    public function __construct(
        private ToolKit $kit,
        private AgentContext $agent,
        private ForumBoardRepository $boards,
        private ForumTopicRepository $topics,
        private WikiPageRepository $pages,
        private FolderRepository $folders,
        private StoredFileRepository $files,
        private FileStorage $storage,
        private ActivityNotifier $notifier,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_forum_boards', 'Forum areas the person can see (with sub-areas as path).', scope: AgentScope::KnowledgeRead)]
    public function listForumBoards(ToolArguments $args, User $user): array
    {
        return ['boards' => array_map(fn (ForumBoard $b): array => [
            'id' => $b->getId(),
            'path' => implode(' / ', array_map(static fn (ForumBoard $p): string => $p->getName(), $b->getPath())),
            'description' => $b->getDescription(),
            'organization' => ToolKit::org($b->getOrganization()),
            'project' => null === $b->getProject() ? null : ['id' => $b->getProject()->getId(), 'name' => $b->getProject()->getName()],
            'url' => $this->kit->url('forum_board_show', ['id' => $b->getId()]),
        ], $this->boards->findVisibleFor($user))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_forum_topics', 'Topics of a forum area, most recently active first.',
        scope: AgentScope::KnowledgeRead,
        properties: ['board_id' => ['type' => 'integer']],
        required: ['board_id'])]
    public function listForumTopics(ToolArguments $args, User $user): array
    {
        $board = $this->board($args);

        return ['topics' => array_map($this->topicSummary(...), \array_slice($this->topics->inBoard($board), 0, 200))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('search_forum', 'Forum topics whose title or posts contain the words.',
        scope: AgentScope::KnowledgeRead,
        properties: ['query' => ['type' => 'string']],
        required: ['query'])]
    public function searchForum(ToolArguments $args, User $user): array
    {
        $found = array_filter($this->topics->search($user, $args->requiredString('query'), 50), fn (ForumTopic $t): bool => $this->agent->allows($t->getOrganization()));

        return ['topics' => array_values(array_map($this->topicSummary(...), $found))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('get_forum_topic', 'A forum topic with all posts.',
        scope: AgentScope::KnowledgeRead,
        properties: ['topic_id' => ['type' => 'integer']],
        required: ['topic_id'])]
    public function getForumTopic(ToolArguments $args, User $user): array
    {
        $topic = $this->topic($args);

        return $this->topicSummary($topic) + ['posts' => array_values(array_map(static fn (ForumPost $p): array => [
            'id' => $p->getId(),
            'author' => ToolKit::person($p->getAuthor()),
            'date' => ToolKit::time($p->getCreatedAt()),
            'text' => ToolKit::excerpt($p->getBody(), self::MAX_TEXT),
            'via_agent' => $p->getAgentName(),
        ], $topic->getPosts()->toArray()))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('create_forum_topic', 'Starts a topic in a forum area; members are notified as usual. Text in Markdown.',
        scope: AgentScope::KnowledgeWrite,
        properties: [
            'board_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'text' => ['type' => 'string'],
            'project_id' => ['type' => 'integer'],
        ],
        required: ['board_id', 'title', 'text'],
        readOnly: false)]
    public function createForumTopic(ToolArguments $args, User $user): array
    {
        $board = $this->board($args);
        $topic = (new ForumTopic($board, $user))->setTitle($args->requiredString('title'));
        if ($args->has('project_id')) {
            $topic->setProject($this->kit->project($user, $args->int('project_id')));
        }
        $post = (new ForumPost($topic, $user))->setBody($args->requiredString('text'));
        $topic->addPost($post);
        $this->kit->validate($topic);
        $this->kit->validate($post);
        $this->kit->em->persist($topic);
        $this->kit->em->persist(new ForumTopicRead($user, $topic));
        $this->kit->em->flush();
        $this->notifier->topicCreated($topic, $post, $user);
        $this->kit->em->flush();

        return $this->topicSummary($topic);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('reply_forum_topic', 'Adds a reply to a forum topic; participants are notified as usual. Text in Markdown.',
        scope: AgentScope::KnowledgeWrite,
        properties: ['topic_id' => ['type' => 'integer'], 'text' => ['type' => 'string']],
        required: ['topic_id', 'text'],
        readOnly: false)]
    public function replyForumTopic(ToolArguments $args, User $user): array
    {
        $topic = $this->topic($args);
        $post = (new ForumPost($topic, $user))->setBody($args->requiredString('text'));
        $this->kit->validate($post);
        $topic->addPost($post);
        $this->kit->em->persist($post);
        $this->kit->em->flush();
        $this->notifier->postAdded($post, $user);
        $this->kit->em->flush();

        return ['post_id' => $post->getId(), 'url' => $this->kit->url('forum_topic_show', ['id' => $topic->getId()]).'#post-'.$post->getId()];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('search_wiki', 'Handbook pages whose title or text contain the words (empty query = all pages).',
        scope: AgentScope::KnowledgeRead,
        properties: ['query' => ['type' => 'string']])]
    public function searchWiki(ToolArguments $args, User $user): array
    {
        $found = array_filter($this->pages->findVisibleFor($user, $args->string('query')), fn (WikiPage $p): bool => $this->agent->allows($p->getOrganization()));

        return ['pages' => array_map(fn (WikiPage $p): array => $this->pageSummary($p) + ['excerpt' => ToolKit::excerpt($p->getBody(), 300)], \array_slice($found, 0, 200))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('get_wiki_page', 'A handbook page with its full text (Markdown) and sub-pages.',
        scope: AgentScope::KnowledgeRead,
        properties: ['page_id' => ['type' => 'integer']],
        required: ['page_id'])]
    public function getWikiPage(ToolArguments $args, User $user): array
    {
        $page = $this->page($args->requiredInt('page_id'));

        return $this->pageSummary($page) + [
            'text' => ToolKit::excerpt($page->getBody(), 100000),
            'children' => array_values(array_map(static fn (WikiPage $c): array => ['id' => $c->getId(), 'title' => $c->getTitle()], $page->getChildren()->toArray())),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('create_wiki_page', 'Creates a handbook page (Markdown), optionally below a parent page.',
        scope: AgentScope::KnowledgeWrite,
        properties: [
            'title' => ['type' => 'string'],
            'text' => ['type' => 'string'],
            'parent_id' => ['type' => 'integer'],
            'organization_id' => ['type' => 'integer', 'description' => 'Without parent: needed when the person belongs to several organizations'],
        ],
        required: ['title', 'text'],
        readOnly: false)]
    public function createWikiPage(ToolArguments $args, User $user): array
    {
        $parent = $args->has('parent_id') ? $this->page($args->requiredInt('parent_id')) : null;
        $organization = $parent?->getOrganization() ?? $this->kit->organization($user, Feature::Wiki, $args->int('organization_id'));
        if (null === $organization) {
            throw new ToolError('No organization of the person uses the handbook and allows AI agents.');
        }
        $page = (new WikiPage($organization))->setParent($parent)->setTitle($args->requiredString('title'))->setBody($args->string('text'));
        $this->kit->check(WikiVoter::EDIT, $page, $organization);
        $this->kit->validate($page);
        $this->kit->em->persist($page);
        $this->kit->em->persist($page->record($user));
        $this->kit->em->flush();

        return $this->pageSummary($page);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('update_wiki_page', 'Changes title and/or text of a handbook page; the previous version stays in the history. Pass the whole new text.',
        scope: AgentScope::KnowledgeWrite,
        properties: ['page_id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'text' => ['type' => 'string']],
        required: ['page_id'],
        readOnly: false,
        idempotent: true)]
    public function updateWikiPage(ToolArguments $args, User $user): array
    {
        $page = $this->page($args->requiredInt('page_id'));
        $before = [$page->getTitle(), $page->getBody()];
        if ($args->has('title')) {
            $page->setTitle($args->string('title'));
        }
        if ($args->has('text')) {
            $page->setBody($args->string('text'));
        }
        $this->kit->validate($page);
        if ($before !== [$page->getTitle(), $page->getBody()]) {
            $this->kit->em->persist($page->record($user));
        }
        $this->kit->em->flush();

        return $this->pageSummary($page);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_folders', 'File folders the person can see, with the number of files.', scope: AgentScope::KnowledgeRead)]
    public function listFolders(ToolArguments $args, User $user): array
    {
        return ['folders' => array_map(fn (Folder $f): array => [
            'id' => $f->getId(),
            'path' => implode(' / ', array_map(static fn (Folder $p): string => $p->getName(), $f->getPath())),
            'files' => $f->getFiles()->count(),
            'organization' => ToolKit::org($f->getOrganization()),
            'url' => $this->kit->url('folder_show', ['id' => $f->getId()]),
        ], $this->folders->findVisibleFor($user))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('search_files', 'Files by name, optionally in one folder (empty query = all files of the folder).',
        scope: AgentScope::KnowledgeRead,
        properties: ['query' => ['type' => 'string'], 'folder_id' => ['type' => 'integer']])]
    public function searchFiles(ToolArguments $args, User $user): array
    {
        $folders = $this->folders->findVisibleFor($user);
        if ($args->has('folder_id')) {
            $folders = array_values(array_filter($folders, static fn (Folder $f): bool => $f->getId() === $args->int('folder_id')));
            if ([] === $folders) {
                throw new ToolError(ToolKit::NOT_FOUND);
            }
        }
        $query = $args->string('query');
        if ('' === $query && !$args->has('folder_id')) {
            throw new ToolError('Pass a query or a folder_id.');
        }
        $found = '' === $query ? $this->files->inFolders($folders) : $this->files->searchInFolders($folders, $query, 100);

        return ['files' => array_map($this->fileSummary(...), \array_slice($found, 0, 200))];
    }

    #[Tool('get_file', 'Content of a file: text directly, images and PDFs as content blocks (up to 4 MB).',
        scope: AgentScope::KnowledgeRead,
        properties: ['file_id' => ['type' => 'integer']],
        required: ['file_id'])]
    public function getFile(ToolArguments $args, User $user): ToolResult
    {
        $file = $this->kit->find(StoredFile::class, $args->requiredInt('file_id'));
        $this->kit->check(FolderVoter::VIEW, $file, $file->getOrganization());

        return ToolKit::fileResult($this->fileSummary($file), $this->storage->absolutePath($file->getStoragePath()), $file->getFilename(), $file->getMimeType(), $file->getSize());
    }

    private function board(ToolArguments $args): ForumBoard
    {
        $board = $this->kit->find(ForumBoard::class, $args->requiredInt('board_id'));
        $this->kit->check(ForumVoter::VIEW, $board, $board->getOrganization());

        return $board;
    }

    private function topic(ToolArguments $args): ForumTopic
    {
        $topic = $this->kit->find(ForumTopic::class, $args->requiredInt('topic_id'));
        $this->kit->check(ForumVoter::VIEW, $topic, $topic->getOrganization());

        return $topic;
    }

    private function page(int $id): WikiPage
    {
        $page = $this->kit->find(WikiPage::class, $id);
        $this->kit->check(WikiVoter::EDIT, $page, $page->getOrganization());

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private function topicSummary(ForumTopic $topic): array
    {
        return [
            'id' => $topic->getId(),
            'title' => $topic->getTitle(),
            'board' => ['id' => $topic->getBoard()->getId(), 'name' => $topic->getBoard()->getName()],
            'organization' => ToolKit::org($topic->getOrganization()),
            'project' => null === $topic->getProject() ? null : ['id' => $topic->getProject()->getId(), 'name' => $topic->getProject()->getName()],
            'started_by' => ToolKit::person($topic->getCreatedBy()),
            'posts' => $topic->getPostCount(),
            'last_post' => ToolKit::time($topic->getLastPostAt()),
            'url' => $this->kit->url('forum_topic_show', ['id' => $topic->getId()]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageSummary(WikiPage $page): array
    {
        return [
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'path' => implode(' / ', array_map(static fn (WikiPage $p): string => $p->getTitle(), $page->getPath())),
            'organization' => ToolKit::org($page->getOrganization()),
            'updated' => ToolKit::time($page->getUpdatedAt()),
            'updated_by' => ToolKit::person($page->getUpdatedBy()),
            'url' => $this->kit->url('wiki_show', ['id' => $page->getId()]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileSummary(StoredFile $file): array
    {
        return [
            'id' => $file->getId(),
            'filename' => $file->getFilename(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'folder' => ['id' => $file->getFolder()->getId(), 'name' => $file->getFolder()->getName()],
            'organization' => ToolKit::org($file->getOrganization()),
            'uploaded' => ToolKit::time($file->getCreatedAt()),
            'url' => $this->kit->url('file_show', ['id' => $file->getId()]),
        ];
    }
}
