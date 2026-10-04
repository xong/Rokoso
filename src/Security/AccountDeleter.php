<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Draft;
use App\Entity\ForumTopicRead;
use App\Entity\Membership;
use App\Entity\MessageRead;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\PushSubscription;
use App\Entity\ResetPasswordRequest;
use App\Entity\ShelfItem;
use App\Entity\Signature;
use App\Entity\User;
use App\Entity\Watch;
use App\Repository\OrganizationRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deletes an account: personal data and private items are removed, the user row stays
 * anonymised so shared content (posts, minutes, resolutions) keeps working.
 */
final readonly class AccountDeleter
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrganizationRepository $organizations,
        private ImageUploader $images,
        private SecurityLog $log,
    ) {
    }

    /**
     * Organizations in which the user is the only administrator – those need a successor
     * (or must be deleted) first.
     *
     * @return list<Organization>
     */
    public function blockers(User $user): array
    {
        return array_values(array_filter(
            $this->organizations->findForUser($user),
            static fn (Organization $o): bool => $o->isAdmin($user) && 1 === $o->countAdmins(),
        ));
    }

    public function delete(User $user, User $actor): void
    {
        foreach ([Membership::class => 'user', Draft::class => 'owner', ShelfItem::class => 'owner', Notification::class => 'recipient',
            PushSubscription::class => 'user', Signature::class => 'user', ResetPasswordRequest::class => 'user', Watch::class => 'user',
            ForumTopicRead::class => 'user', MessageRead::class => 'user'] as $class => $field) {
            $this->em->createQueryBuilder()->delete($class, 'x')->where('x.'.$field.' = :user')->setParameter('user', $user)->getQuery()->execute();
        }
        $this->images->remove($user->getAvatar());
        $user->anonymize();
        $this->em->flush();
        $this->log->record('account_deleted', $user, null, $actor);
    }
}
