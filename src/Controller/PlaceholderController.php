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

    #[Route('/calendar', name: 'calendar_month', defaults: ['title' => 'nav.calendar', 'phase' => 10])]
    public function placeholder(string $title, int $phase): Response
    {
        return $this->render('placeholder.html.twig', ['title' => $title, 'phase' => $phase]);
    }
}
