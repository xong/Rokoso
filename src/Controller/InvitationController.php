<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\InvitationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Invitation links: accept when logged in with the invited address, otherwise log in or register first.
 */
final class InvitationController extends AbstractController
{
    use TargetPathTrait;

    public const string SESSION_KEY = 'invitation_token';

    public function __construct(private readonly InvitationManager $manager)
    {
    }

    #[Route('/invitation/{token}', name: 'invitation_show', methods: ['GET'])]
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->manager->findValid($token);
        $user = $this->getUser();

        if (!$user instanceof User && null !== $invitation) {
            $request->getSession()->set(self::SESSION_KEY, $token);
            $this->saveTargetPath($request->getSession(), 'main', $request->getUri());
        }

        return $this->render('invitation/show.html.twig', [
            'invitation' => $invitation,
            'already_member' => $user instanceof User && null !== $invitation?->getOrganization()->getMembership($user),
            'wrong_account' => $user instanceof User && null !== $invitation && !$this->manager->isFor($invitation, $user),
            'has_account' => !$user instanceof User && null !== $invitation && $this->manager->hasAccount($invitation),
        ]);
    }

    #[Route('/invitation/{token}/accept', name: 'invitation_accept', methods: ['POST'])]
    #[IsCsrfTokenValid('invitation-accept')]
    public function accept(string $token): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $invitation = $this->manager->findValid($token);
        if (null === $invitation) {
            throw $this->createNotFoundException();
        }
        if (!$this->manager->isFor($invitation, $user)) {
            $this->addFlash('error', 'invitation.not_for_you');

            return $this->redirectToRoute('invitation_show', ['token' => $token]);
        }

        $organization = $this->manager->join($invitation, $user);
        $this->addFlash('success', 'invitation.joined');

        return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
    }
}
