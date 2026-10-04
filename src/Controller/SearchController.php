<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Search\GlobalSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request, #[CurrentUser] User $user, GlobalSearch $search): Response
    {
        $query = trim($request->query->getString('q'));
        $groups = $search->search($user, $query);

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'groups' => $groups,
            'count' => array_sum(array_map(\count(...), $groups)),
            'too_short' => '' !== $query && mb_strlen($query) < GlobalSearch::MIN_LENGTH,
        ]);
    }
}
