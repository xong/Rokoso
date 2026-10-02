<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * When a user last read a topic; newer posts make the topic unread.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'forum_read_user_topic', columns: ['user_id', 'topic_id'])]
class ForumTopicRead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $readAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ForumTopic $topic,
    ) {
        $this->readAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTopic(): ForumTopic
    {
        return $this->topic;
    }

    public function getReadAt(): \DateTimeImmutable
    {
        return $this->readAt;
    }

    public function touch(): static
    {
        $this->readAt = new \DateTimeImmutable();

        return $this;
    }
}
