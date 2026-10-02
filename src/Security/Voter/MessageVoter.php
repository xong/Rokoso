<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * MESSAGE_VIEW: see, comment, assign, trash (shared mailbox: everyone who sees a message may work on it).
 * Rules are defined once in MessageRepository::visibleQuery().
 *
 * @extends Voter<string, Message>
 */
final class MessageVoter extends Voter
{
    public const string VIEW = 'MESSAGE_VIEW';

    public function __construct(private readonly MessageRepository $messages)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof Message;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->messages->isVisibleTo($subject, $user);
    }
}
