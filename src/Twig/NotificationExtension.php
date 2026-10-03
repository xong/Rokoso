<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\ForumBoard;
use App\Entity\ForumTopic;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Notification\NotificationText;
use App\Repository\WatchRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final readonly class NotificationExtension
{
    public function __construct(
        private NotificationText $text,
        private WatchRepository $watches,
        private Security $security,
    ) {
    }

    #[AsTwigFilter('notification_text')]
    public function text(Notification $notification): string
    {
        return $this->text->text($notification);
    }

    #[AsTwigFunction('is_watching')]
    public function isWatching(ForumBoard|ForumTopic|Project $target): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && $this->watches->isWatching($user, $target);
    }

    /**
     * Names offered by the @-mention autocomplete (members of the organization, without oneself).
     *
     * @return list<string>
     */
    #[AsTwigFunction('mention_names')]
    public function mentionNames(?Organization $organization): array
    {
        if (null === $organization) {
            return [];
        }
        $me = $this->security->getUser();
        $names = [];
        foreach ($organization->getMembers() as $member) {
            if ($member !== $me) {
                $names[] = $member->getName();
            }
        }
        sort($names, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $names;
    }
}
