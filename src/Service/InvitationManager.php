<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\OrganizationRole;
use App\Notification\NotificationCenter;
use App\Repository\InvitationRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Invitations to an organization. The link only works for the invited address: people with an account
 * also get a notification in Rokoso, everyone gets an email (worded for login or registration). If the email
 * cannot be sent, the invitation stays: admins can copy its link from the list of open invitations.
 */
final readonly class InvitationManager
{
    public function __construct(
        private InvitationRepository $invitations,
        private UserRepository $users,
        private NotificationRepository $notifications,
        private NotificationCenter $notificationCenter,
        private SystemMailer $mailer,
        private UrlGeneratorInterface $urls,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function findValid(?string $token): ?Invitation
    {
        if (null === $token || '' === $token) {
            return null;
        }
        $invitation = $this->invitations->findOneBy(['token' => $token]);

        return null === $invitation || $invitation->isExpired() ? null : $invitation;
    }

    /**
     * Creates the invitation, notifies an existing account and sends the email.
     */
    public function invite(Organization $organization, string $email, OrganizationRole $role, User $inviter): InvitationResult
    {
        $invitation = new Invitation($organization, $email, $role, $inviter);
        $this->em->persist($invitation);
        $this->em->flush();

        $path = $this->urls->generate('invitation_show', ['token' => $invitation->getToken()]);
        $existing = $this->users->findOneBy(['email' => $invitation->getEmail()]);
        if (null !== $existing) {
            // the invitation email is sent below, so no second email for the notification
            $this->notificationCenter->notify([$existing], NotificationType::Invitation, $organization->getName(), $path, $inviter, refKey: self::refKey($invitation), email: false);
            $this->em->flush();
        }

        try {
            $this->mailer->send($invitation->getEmail(), 'email.invitation.subject', 'invitation', [
                'inviter' => $inviter->getName(),
                'organization' => $organization->getName(),
                'url' => $this->urls->generate('invitation_show', ['token' => $invitation->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
                'has_account' => null !== $existing,
                'subject_params' => ['%organization%' => $organization->getName()],
            ]);
            $mailed = true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Invitation mail failed: {message}', ['message' => $e->getMessage()]);
            $mailed = false;
        }

        return new InvitationResult($invitation, null !== $existing, $mailed);
    }

    public function hasAccount(Invitation $invitation): bool
    {
        return null !== $this->users->findOneBy(['email' => $invitation->getEmail()]);
    }

    /** Only the invited address may accept (the link could have been forwarded). */
    public function isFor(Invitation $invitation, User $user): bool
    {
        return $invitation->getEmail() === $user->getEmail();
    }

    /**
     * Adds the user to the invited organization and consumes the invitation. Former members and guests
     * get the invited role and a new open term.
     */
    public function join(Invitation $invitation, User $user): Organization
    {
        if (!$this->isFor($invitation, $user)) {
            throw new \LogicException('The invitation is meant for another address.');
        }
        $organization = $invitation->getOrganization();
        $membership = $organization->findMembership($user);
        if (null === $membership) {
            $organization->addMember($user, $invitation->getRole());
        } elseif (!$membership->isFull()) {
            $membership->setRole($invitation->getRole())->setTermEndsOn(null);
        }
        $this->remove($invitation);

        return $organization;
    }

    public function revoke(Invitation $invitation): void
    {
        $this->remove($invitation);
    }

    private function remove(Invitation $invitation): void
    {
        $this->notifications->deleteByRef(self::refKey($invitation));
        $this->em->remove($invitation);
        $this->em->flush();
    }

    private static function refKey(Invitation $invitation): string
    {
        return 'invitation:'.$invitation->getId();
    }
}
