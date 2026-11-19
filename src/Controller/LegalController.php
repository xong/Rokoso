<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\LegalDocument;
use App\Repository\LegalTextRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public privacy policy and imprint of the installation; 404 as long as the text is empty.
 */
final class LegalController extends AbstractController
{
    #[Route('/{document<privacy|imprint>}', name: 'legal', methods: ['GET'])]
    public function show(LegalDocument $document, LegalTextRepository $texts): Response
    {
        $text = $texts->get($document);
        if (!$document->isPage() || '' === $text->getContent()) {
            throw $this->createNotFoundException();
        }

        return $this->render('legal/show.html.twig', ['text' => $text]);
    }
}
