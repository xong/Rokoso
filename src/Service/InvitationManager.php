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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Invitations to an organization. The link only works for the invited address. Rokoso sends no invitation
 * email itself (the invited person has not agreed to mails from the platform): the inviting admin passes the
 * link on personally, e.g. from the organization's mailbox with the prepared {@see text()}. People with an
 * account also get a notification in Rokoso (by email only if they chose so).
 */
final readonly class InvitationManager
{
    public function __construct(
        private InvitationRepository $invitations,
        private UserRepository $users,
        private NotificationRepository $notifications,
        private NotificationCenter $notificationCenter,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
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
     * Creates the invitation and notifies an existing account (no email to the invited address).
     */
    public function invite(Organization $organization, string $email, OrganizationRole $role, User $inviter): Invitation
    {
        $invitation = new Invitation($organization, $email, $role, $inviter);
        $this->em->persist($invitation);
        $this->em->flush();

        $existing = $this->users->findOneBy(['email' => $invitation->getEmail()]);
        if (null !== $existing) {
            $path = $this->urls->generate('invitation_show', ['token' => $invitation->getToken()]);
            $this->notificationCenter->notify([$existing], NotificationType::Invitation, $organization->getName(), $path, $inviter, refKey: self::refKey($invitation));
            $this->em->flush();
        }

        return $invitation;
    }

    public function url(Invitation $invitation): string
    {
        return $this->urls->generate('invitation_show', ['token' => $invitation->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function subject(Invitation $invitation): string
    {
        return $this->translator->trans('invitation.send.subject', ['%organization%' => $invitation->getOrganization()->getName()]);
    }

    /** Prepared message with the link, for the inviting person to send personally */
    public function text(Invitation $invitation, User $inviter): string
    {
        return $this->translator->trans('invitation.send.text', [
            '%organization%' => $invitation->getOrganization()->getName(),
            '%url%' => $this->url($invitation),
            '%email%' => $invitation->getEmail(),
            '%account%' => $this->translator->trans($this->hasAccount($invitation) ? 'invitation.send.has_account' : 'invitation.send.no_account', ['%email%' => $invitation->getEmail()]),
            '%date%' => self::validUntil($invitation)->format('d.m.Y'),
            '%inviter%' => $inviter->getName(),
        ]);
    }

    public static function validUntil(Invitation $invitation): \DateTimeImmutable
    {
        return $invitation->getCreatedAt()->modify(Invitation::VALIDITY);
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
