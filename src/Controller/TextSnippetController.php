<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\TextSnippet;
use App\Entity\User;
use App\Form\TextSnippetFormType;
use App\Repository\OrganizationRepository;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Text snippets of an organization: every full member may add, change and delete them.
 */
#[Route('/organizations')]
final class TextSnippetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    #[Route('/{id<\d+>}/snippets/new', name: 'snippet_new')]
    public function new(Request $request, Organization $organization, #[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        return $this->form($request, new TextSnippet($organization), $user);
    }

    #[Route('/snippets/{id<\d+>}/edit', name: 'snippet_edit')]
    public function edit(Request $request, TextSnippet $snippet, #[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $snippet->getOrganization());

        return $this->form($request, $snippet, $user);
    }

    #[Route('/snippets/{id<\d+>}/delete', name: 'snippet_delete', methods: ['POST'])]
    public function delete(Request $request, TextSnippet $snippet): Response
    {
        $organization = $snippet->getOrganization();
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);
        if ($this->isCsrfTokenValid('snippet-delete', $request->getPayload()->getString('_token'))) {
            $this->em->remove($snippet);
            $this->em->flush();
            $this->addFlash('success', 'snippet.deleted');
        }

        return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
    }

    private function form(Request $request, TextSnippet $snippet, User $user): Response
    {
        $form = $this->createForm(TextSnippetFormType::class, $snippet);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($snippet);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('organization_show', ['id' => $snippet->getOrganization()->getId()]);
        }

        return $this->render('organization/snippet.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $snippet->getOrganization(),
            'snippet' => $snippet,
            'form' => $form,
        ]);
    }
}
