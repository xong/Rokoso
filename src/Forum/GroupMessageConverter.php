<?php

declare(strict_types=1);

namespace App\Forum;

use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\ForumUpload;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\MessageType;
use App\Service\AttachmentStorage;
use App\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns internal messages addressed to an organization or project ("group messages") into forum
 * topics: replies and comments become posts, attachments move to the forum storage. Internal
 * messages to individual people stay as they are.
 */
final readonly class GroupMessageConverter
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttachmentStorage $attachments,
        private FileStorage $files,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return int number of created topics
     */
    public function convert(): int
    {
        /** @var list<Message> $messages */
        $messages = $this->em->createQueryBuilder()
            ->select('m')->from(Message::class, 'm')
            ->andWhere('m.type = :internal')
            ->andWhere('m.organization IS NOT NULL')
            ->setParameter('internal', MessageType::Internal->value)
            ->orderBy('m.date', \SortDirection::Ascending)
            ->getQuery()->getResult();

        // keyed by spl_object_id: the entities are loaded once, so identity is stable
        $topics = [];
        $boards = [];
        $converted = [];
        foreach ($messages as $message) {
            $organization = $message->getOrganization();
            \assert($organization instanceof Organization);
            $parent = $this->original($message, $messages);
            if (null !== $parent && isset($topics[spl_object_id($parent)])) {
                $topic = $topics[spl_object_id($parent)];
            } else {
                $board = $boards[spl_object_id($organization)] ??= $this->board($organization, $message->getAuthor());
                $topic = (new ForumTopic($board, $message->getAuthor()))
                    ->setTitle($message->getSubject() ?: $this->translator->trans('mail.no_subject'))
                    ->setProject($message->getProject())
                    ->backdate($message->getDate());
                $this->em->persist($topic);
            }
            $topics[spl_object_id($message)] = $topic;

            $post = $this->post($topic, $message->getAuthor(), $this->text($message), $message->getDate());
            foreach ($message->getAttachments() as $attachment) {
                $upload = new ForumUpload(
                    $topic->getBoard(),
                    $attachment->getFilename(),
                    $attachment->getMimeType(),
                    $attachment->getSize(),
                    $this->files->store($this->attachments->read($attachment->getStoragePath())),
                    $message->getAuthor(),
                );
                $this->em->persist($upload->setPost($post));
            }
            foreach ($message->getComments() as $comment) {
                $this->post($topic, $comment->getAuthor(), $comment->getBody(), $comment->getCreatedAt());
            }
            $converted[] = $message;
        }
        $this->em->flush();

        $paths = [];
        foreach ($converted as $message) {
            foreach ($message->getAttachments() as $attachment) {
                $paths[] = $attachment->getStoragePath();
            }
            $this->em->remove($message);
        }
        $this->em->flush();
        foreach ($paths as $path) {
            $this->attachments->remove($path);
        }

        return \count(array_unique(array_map(spl_object_id(...), $topics)));
    }

    /**
     * The converted message this one replies to (in-reply-to holds the message id for internal replies).
     *
     * @param list<Message> $messages
     */
    private function original(Message $message, array $messages): ?Message
    {
        $id = (int) $message->getInReplyTo();
        foreach ($messages as $candidate) {
            if ($id > 0 && $candidate->getId() === $id) {
                return $candidate;
            }
        }

        return null;
    }

    private function board(Organization $organization, ?User $author): ForumBoard
    {
        $name = $this->translator->trans('internal.converted_board');
        $board = $this->em->getRepository(ForumBoard::class)->findOneBy(['organization' => $organization, 'name' => $name]);
        if (null === $board) {
            $board = (new ForumBoard($organization, $author))->setName($name);
            $this->em->persist($board);
        }

        return $board;
    }

    private function post(ForumTopic $topic, ?User $author, string $body, \DateTimeImmutable $at): ForumPost
    {
        $post = (new ForumPost($topic, $author))->setBody($body)->backdate($at);
        $topic->addPost($post);
        $this->em->persist($post);

        return $post;
    }

    private function text(Message $message): string
    {
        $text = $message->getTextBody();
        if (null === $text || '' === trim($text)) {
            $text = html_entity_decode(strip_tags((string) $message->getHtmlBody()), \ENT_QUOTES | \ENT_HTML5);
        }

        return '' === trim($text) ? '–' : $text;
    }
}
