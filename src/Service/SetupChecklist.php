<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\CalendarItemRepository;
use App\Repository\ForumBoardRepository;
use App\Repository\InvitationRepository;
use App\Repository\MailAccountRepository;
use App\Repository\OrganizationRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Setup assistant on the start page: the first steps a new team takes, each marked done
 * as soon as the data exists. Shown until all steps are done or the user hides it.
 */
final readonly class SetupChecklist
{
    public function __construct(
        private OrganizationRepository $organizations,
        private InvitationRepository $invitations,
        private MailAccountRepository $accounts,
        private ForumBoardRepository $boards,
        private CalendarItemRepository $items,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * Steps for the user, or null if the checklist should not be shown.
     *
     * @return list<array{key: string, done: bool, url: string}>|null
     */
    public function stepsFor(User $user): ?array
    {
        if ($user->isSetupDismissed()) {
            return null;
        }

        $organizations = $this->organizations->findForUser($user);
        $admin = array_values(array_filter($organizations, static fn (Organization $o): bool => $o->isAdmin($user)));
        // only for people who set things up: without an organization or as admin of one
        if ([] !== $organizations && [] === $admin) {
            return null;
        }
        $first = $admin[0] ?? null;
        $show = null !== $first ? $this->urls->generate('organization_show', ['id' => $first->getId()]) : null;

        $steps = [
            [
                'key' => 'organization',
                'done' => null !== $first,
                'url' => $this->urls->generate('organization_new'),
            ],
            [
                'key' => 'members',
                'done' => [] !== array_filter($admin, fn (Organization $o): bool => \count($o->getMembers()) > 1 || [] !== $this->invitations->findPending($o)),
                'url' => $show.'#members-heading',
            ],
            [
                'key' => 'mail_account',
                'done' => [] !== $this->accounts->findForUser($user),
                'url' => null !== $first ? $this->urls->generate('mail_account_new', ['id' => $first->getId()]) : '',
            ],
            [
                'key' => 'board',
                'done' => [] !== $this->boards->findVisibleFor($user),
                'url' => $this->urls->generate('forum_board_new'),
            ],
            [
                'key' => 'event',
                'done' => $this->items->hasVisibleEvent($user),
                'url' => $this->urls->generate('calendar_item_new'),
            ],
        ];

        foreach ($steps as $step) {
            if (!$step['done']) {
                return $steps;
            }
        }

        return null;
    }
}
