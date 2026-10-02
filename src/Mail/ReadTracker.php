<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Message;
use App\Entity\MessageRead;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Read/unread state per user.
 */
final readonly class ReadTracker
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function markRead(Message $message, User $user): void
    {
        if (null === $this->find($message, $user)) {
            $this->em->persist(new MessageRead($message, $user));
            $this->em->flush();
        }
    }

    public function markUnread(Message $message, User $user): void
    {
        $read = $this->find($message, $user);
        if (null !== $read) {
            $this->em->remove($read);
            $this->em->flush();
        }
    }

    private function find(Message $message, User $user): ?MessageRead
    {
        return $this->em->getRepository(MessageRead::class)->findOneBy(['message' => $message, 'user' => $user]);
    }
}
