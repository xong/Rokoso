<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Changelog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * "Neuigkeiten": release notes from CHANGELOG.md; reading them hides the hint on the start page.
 */
final class ChangelogController extends AbstractController
{
    public function __construct(
        private readonly Changelog $changelog,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/profile/changelog', name: 'profile_changelog')]
    public function index(#[CurrentUser] User $user): Response
    {
        $this->markSeen($user);

        return $this->render('profile/changelog.html.twig', [
            'section' => 'changelog',
            'releases' => $this->changelog->releases(),
        ]);
    }

    #[Route('/changelog/dismiss', name: 'changelog_dismiss', methods: ['POST'])]
    public function dismiss(Request $request, #[CurrentUser] User $user): Response
    {
        if ($this->isCsrfTokenValid('changelog-dismiss', $request->getPayload()->getString('_token'))) {
            $this->markSeen($user);
        }

        return $this->redirectToRoute('home');
    }

    private function markSeen(User $user): void
    {
        $version = $this->changelog->current()?->version;
        if (null !== $version && $version !== $user->getSeenVersion()) {
            $user->setSeenVersion($version);
            $this->em->flush();
        }
    }
}
