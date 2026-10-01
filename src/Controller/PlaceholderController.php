<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Platzhalter für Bereiche, die in späteren Phasen umgesetzt werden.
 * Routen werden hier entfernt, sobald der echte Controller existiert.
 */
final class PlaceholderController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->redirectToRoute('mail_inbox');
    }

    #[Route('/mail', name: 'mail_inbox', defaults: ['title' => 'nav.mail_inbox', 'phase' => 5])]
    #[Route('/mail/sent', name: 'mail_sent', defaults: ['title' => 'nav.mail_sent', 'phase' => 5])]
    #[Route('/mail/trash', name: 'mail_trash', defaults: ['title' => 'nav.mail_trash', 'phase' => 5])]
    #[Route('/mail/new', name: 'mail_compose', defaults: ['title' => 'nav.mail_compose', 'phase' => 7])]
    #[Route('/calendar', name: 'calendar', defaults: ['title' => 'nav.calendar', 'phase' => 10])]
    #[Route('/contacts', name: 'contacts', defaults: ['title' => 'nav.contacts', 'phase' => 9])]
    #[Route('/projects', name: 'projects', defaults: ['title' => 'nav.projects', 'phase' => 3])]
    #[Route('/organizations', name: 'organizations', defaults: ['title' => 'nav.organizations', 'phase' => 2])]
    public function placeholder(string $title, int $phase): Response
    {
        return $this->render('placeholder.html.twig', ['title' => $title, 'phase' => $phase]);
    }
}
