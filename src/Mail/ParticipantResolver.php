<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Message;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Who can be made responsible for a message, and which projects it can be filed under.
 */
final readonly class ParticipantResolver
{
    public function __construct(
        private ProjectRepository $projects,
        private Security $security,
    ) {
    }

    /**
     * Organization members, or – for personal internal messages – author and recipients.
     *
     * @return list<User>
     */
    public function candidates(Message $message): array
    {
        $users = null !== $message->getOrganization()
            ? $message->getOrganization()->getMembers()
            : array_values(array_filter([$message->getAuthor(), ...$message->getRecipientUsers()->toArray()]));

        $unique = [];
        foreach ($users as $user) {
            $unique[(int) $user->getId()] = $user;
        }
        usort($unique, static fn (User $a, User $b): int => strcasecmp($a->getName(), $b->getName()));

        return $unique;
    }

    /**
     * Projects of the message's organization; for messages without organization the user's own projects.
     *
     * @return list<Project>
     */
    public function projects(Message $message): array
    {
        $organization = $message->getOrganization();
        if (null !== $organization) {
            return $this->projects->findBy(['organization' => $organization], ['name' => 'ASC']);
        }
        $user = $this->security->getUser();

        return $user instanceof User ? $this->projects->findBy(['organization' => null, 'createdBy' => $user], ['name' => 'ASC']) : [];
    }
}
