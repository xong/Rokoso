<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WatchRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * "Beobachten": a person follows a forum board, a topic or a project. Exactly one target is set.
 */
#[ORM\Entity(repositoryClass: WatchRepository::class)]
#[ORM\UniqueConstraint(name: 'watch_board', columns: ['user_id', 'board_id'])]
#[ORM\UniqueConstraint(name: 'watch_topic', columns: ['user_id', 'topic_id'])]
#[ORM\UniqueConstraint(name: 'watch_project', columns: ['user_id', 'project_id'])]
class Watch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ForumBoard $board = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ForumTopic $topic = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Project $project = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        ForumBoard|ForumTopic|Project $target,
    ) {
        match (true) {
            $target instanceof ForumBoard => $this->board = $target,
            $target instanceof ForumTopic => $this->topic = $target,
            $target instanceof Project => $this->project = $target,
        };
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTarget(): ForumBoard|ForumTopic|Project
    {
        return $this->board ?? $this->topic ?? $this->project ?? throw new \LogicException('Watch without target');
    }
}
