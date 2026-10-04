<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\CalendarItem;
use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\Message;
use App\Entity\Project;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Mail\ParticipantResolver;
use App\Repository\CommentRepository;
use App\Repository\WatchRepository;
use App\Security\Voter\ContactVoter;
use App\Security\Voter\FolderVoter;
use App\Security\Voter\ForumVoter;
use App\Security\Voter\MessageVoter;
use App\Security\Voter\ProjectVoter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Decides who is notified about what (comments, mentions, forum, assignments).
 * Mentions come first, so a mentioned person gets one "mentioned" instead of a generic notification.
 */
final readonly class ActivityNotifier
{
    public function __construct(
        private NotificationCenter $center,
        private WatchRepository $watches,
        private CommentRepository $comments,
        private ParticipantResolver $participants,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function commentAdded(Comment $comment, User $actor): void
    {
        $target = $comment->getTarget();
        [$url, $subject, $access, $candidates, $interested] = match (true) {
            $target instanceof Message => [
                $this->urls->generate('mail_show', ['folder' => 'all', 'id' => $target->getId()]),
                $target->getSubject(),
                [MessageVoter::VIEW, $target],
                $this->participants->candidates($target),
                [...$target->getAssignees(), ...array_map(static fn (Comment $c): ?User => $c->getAuthor(), $target->getComments()->toArray())],
            ],
            $target instanceof Project => [
                $this->urls->generate('project_show', ['id' => $target->getId()]),
                $target->getName(),
                [ProjectVoter::VIEW, $target],
                $target->getOrganization()?->getMembers() ?? [],
                [...$this->watches->watchersOf($target), ...$this->authors($this->comments->forTarget($target))],
            ],
            $target instanceof StoredFile => [
                $this->urls->generate('file_show', ['id' => $target->getId()]),
                $target->getFilename(),
                [FolderVoter::VIEW, $target],
                $target->getOrganization()->getMembers(),
                [$target->getUploadedBy(), ...$this->authors($this->comments->forTarget($target))],
            ],
            $target instanceof Contact => [
                $this->urls->generate('contact_show', ['id' => $target->getId()]),
                $target->getDisplayName(),
                [ContactVoter::EDIT, $target],
                $target->getOrganization()?->getMembers() ?? [],
                $this->authors($this->comments->forTarget($target)),
            ],
        };

        $this->center->notify(NotificationCenter::mentions($comment->getBody(), $candidates), NotificationType::Mentioned, $subject, $url, $actor, $access);
        $this->center->notify($interested, NotificationType::Comment, $subject, $url, $actor, $access);
    }

    public function topicCreated(ForumTopic $topic, ForumPost $first, User $actor): void
    {
        $this->watches->watch($actor, $topic);
        $url = $this->topicUrl($topic);
        $access = [ForumVoter::VIEW, $topic->getBoard()];
        $this->center->notify(NotificationCenter::mentions($first->getBody(), $topic->getOrganization()->getMembers()), NotificationType::Mentioned, $topic->getTitle(), $url, $actor, $access);

        $watchers = $this->watches->watchersOf($topic->getBoard());
        foreach (array_filter([$topic->getBoard()->getProject(), $topic->getProject()]) as $project) {
            $watchers = [...$watchers, ...$this->watches->watchersOf($project)];
        }
        $this->center->notify($watchers, NotificationType::Topic, $topic->getTitle(), $url, $actor, $access);
    }

    public function postAdded(ForumPost $post, User $actor): void
    {
        $topic = $post->getTopic();
        $url = $this->topicUrl($topic);
        $access = [ForumVoter::VIEW, $topic->getBoard()];
        $this->center->notify(NotificationCenter::mentions($post->getBody(), $topic->getOrganization()->getMembers()), NotificationType::Mentioned, $topic->getTitle(), $url, $actor, $access);
        $this->center->notify($this->watches->watchersOf($topic), NotificationType::Post, $topic->getTitle(), $url, $actor, $access);
        $this->watches->watch($actor, $topic);
    }

    /**
     * @param iterable<User> $assignees newly assigned people
     */
    public function messageAssigned(Message $message, iterable $assignees, ?User $actor): void
    {
        $this->center->notify($assignees, NotificationType::Assigned, $message->getSubject(),
            $this->urls->generate('mail_show', ['folder' => 'all', 'id' => $message->getId()]), $actor, [MessageVoter::VIEW, $message]);
    }

    /**
     * @param iterable<User> $assignees newly assigned people
     */
    public function calendarAssigned(CalendarItem $item, iterable $assignees, User $actor): void
    {
        $this->center->notify($assignees, NotificationType::Assigned, $item->getTitle(),
            $this->urls->generate('calendar_item_show', ['id' => $item->getId()]), $actor);
    }

    public function topicUrl(ForumTopic $topic): string
    {
        return $this->urls->generate('forum_topic_show', ['id' => $topic->getId()]);
    }

    /**
     * @param list<Comment> $comments
     *
     * @return list<User|null>
     */
    private function authors(array $comments): array
    {
        return array_map(static fn (Comment $c): ?User => $c->getAuthor(), $comments);
    }
}
