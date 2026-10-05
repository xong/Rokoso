<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Help\HelpArticle;
use App\Help\HelpCenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Built-in help (articles from help/, see {@see HelpCenter}).
 */
#[Route('/help')]
final class HelpController extends AbstractController
{
    public function __construct(private readonly HelpCenter $help)
    {
    }

    #[Route('', name: 'help_index')]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('help/index.html.twig', $this->listContext($user));
    }

    #[Route('/{slug<[a-z0-9-]+>}', name: 'help_show')]
    public function show(string $slug, #[CurrentUser] User $user): Response
    {
        $article = $this->help->find($slug, $user) ?? throw $this->createNotFoundException();

        return $this->render('help/show.html.twig', ['article' => $article] + $this->listContext($user));
    }

    /**
     * @return array{areas: list<HelpArticle>, guides: list<HelpArticle>}
     */
    private function listContext(User $user): array
    {
        $articles = $this->help->availableTo($user);

        return [
            'areas' => array_values(array_filter($articles, static fn (HelpArticle $a): bool => !$a->guide)),
            'guides' => array_values(array_filter($articles, static fn (HelpArticle $a): bool => $a->guide)),
        ];
    }
}
