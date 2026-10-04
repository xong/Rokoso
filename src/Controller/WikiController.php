<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\WikiPage;
use App\Entity\WikiRevision;
use App\Enum\Feature;
use App\Form\WikiPageFormType;
use App\Repository\OrganizationRepository;
use App\Repository\WikiPageRepository;
use App\Security\Voter\WikiVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Knowledge base (handbook) per organization: nested Markdown pages with revisions.
 */
#[Route('/knowledge')]
final class WikiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WikiPageRepository $pages,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    #[Route('', name: 'wiki_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render('wiki/index.html.twig', $this->listContext($request, $user));
    }

    #[Route('/{id<\d+>}', name: 'wiki_show')]
    #[IsGranted(WikiVoter::EDIT, 'page')]
    public function show(Request $request, WikiPage $page, #[CurrentUser] User $user): Response
    {
        return $this->render('wiki/show.html.twig', ['page' => $page] + $this->listContext($request, $user, $page));
    }

    /**
     * New page in an organization (?organization=), optionally below another page (?parent=).
     */
    #[Route('/new', name: 'wiki_new')]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $parent = $this->pages->find($request->query->getInt('parent'));
        if (null !== $parent) {
            $this->denyAccessUnlessGranted(WikiVoter::EDIT, $parent);
            $organization = $parent->getOrganization();
        } else {
            $choices = $this->organizations->findForUser($user, Feature::Wiki);
            $organization = $this->organizations->find($request->query->getInt('organization'));
            $organization = \in_array($organization, $choices, true) ? $organization : ($choices[0] ?? null);
            if (null === $organization) {
                throw $this->createAccessDeniedException();
            }
        }
        $page = new WikiPage($organization);
        $page->setParent($parent);

        return $this->handleForm($request, $user, $page);
    }

    #[Route('/{id<\d+>}/edit', name: 'wiki_edit')]
    #[IsGranted(WikiVoter::EDIT, 'page')]
    public function edit(Request $request, WikiPage $page, #[CurrentUser] User $user): Response
    {
        return $this->handleForm($request, $user, $page);
    }

    #[Route('/{id<\d+>}/delete', name: 'wiki_delete', methods: ['POST'])]
    #[IsGranted(WikiVoter::DELETE, 'page')]
    #[IsCsrfTokenValid('wiki-delete')]
    public function delete(WikiPage $page): Response
    {
        $parent = $page->getParent();
        $this->em->remove($page);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return null === $parent ? $this->redirectToRoute('wiki_index') : $this->redirectToRoute('wiki_show', ['id' => $parent->getId()]);
    }

    #[Route('/{id<\d+>}/revisions', name: 'wiki_revisions')]
    #[IsGranted(WikiVoter::EDIT, 'page')]
    public function revisions(Request $request, WikiPage $page, #[CurrentUser] User $user): Response
    {
        return $this->render('wiki/revisions.html.twig', ['page' => $page, 'revision' => null] + $this->listContext($request, $user, $page));
    }

    #[Route('/revisions/{id<\d+>}', name: 'wiki_revision')]
    public function revision(Request $request, WikiRevision $revision, #[CurrentUser] User $user): Response
    {
        $page = $revision->getPage();
        $this->denyAccessUnlessGranted(WikiVoter::EDIT, $page);

        return $this->render('wiki/revisions.html.twig', ['page' => $page, 'revision' => $revision] + $this->listContext($request, $user, $page));
    }

    /**
     * Makes an old state the current one (as a new revision, nothing is lost).
     */
    #[Route('/revisions/{id<\d+>}/restore', name: 'wiki_revision_restore', methods: ['POST'])]
    #[IsCsrfTokenValid('wiki-restore')]
    public function restore(WikiRevision $revision, #[CurrentUser] User $user): Response
    {
        $page = $revision->getPage();
        $this->denyAccessUnlessGranted(WikiVoter::EDIT, $page);
        $page->setTitle($revision->getTitle())->setBody($revision->getBody());
        $this->em->persist($page->record($user));
        $this->em->flush();
        $this->addFlash('success', 'wiki.restored');

        return $this->redirectToRoute('wiki_show', ['id' => $page->getId()]);
    }

    private function handleForm(Request $request, User $user, WikiPage $page): Response
    {
        $isNew = null === $page->getId();
        $before = [$page->getTitle(), $page->getBody()];
        $parents = array_values(array_filter($this->tree($this->pages->findVisibleFor($user), $page->getOrganization()), static fn (WikiPage $p): bool => !$page->contains($p)));
        $form = $this->createForm(WikiPageFormType::class, $page, ['pages' => $parents]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $this->em->persist($page);
            }
            // a revision only when the content changed (moving a page is not a new version)
            if ($isNew || $before !== [$page->getTitle(), $page->getBody()]) {
                $this->em->persist($page->record($user));
            }
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('wiki_show', ['id' => $page->getId()]);
        }

        return $this->render('wiki/form.html.twig', [
            'form' => $form,
            'page' => $page,
            'is_new' => $isNew,
        ] + $this->listContext($request, $user, $isNew ? null : $page));
    }

    /**
     * Pages of one organization in tree order (parents before their children).
     *
     * @param list<WikiPage> $pages
     *
     * @return list<WikiPage>
     */
    private function tree(array $pages, Organization $organization): array
    {
        $all = array_filter($pages, static fn (WikiPage $p): bool => $p->getOrganization() === $organization);
        $sorted = [];
        $walk = static function (?WikiPage $parent) use (&$walk, &$sorted, $all): void {
            foreach ($all as $p) {
                if ($p->getParent() === $parent) {
                    $sorted[] = $p;
                    $walk($p);
                }
            }
        };
        $walk(null);

        return $sorted;
    }

    /**
     * Middle column: page tree per organization, or hits of the search.
     *
     * @return array<string, mixed>
     */
    private function listContext(Request $request, User $user, ?WikiPage $current = null): array
    {
        $query = trim($request->query->getString('q'));
        $organizations = $this->organizations->findForUser($user, Feature::Wiki);
        $pages = $this->pages->findVisibleFor($user);

        return [
            'q' => $query,
            'hits' => '' === $query ? null : $this->pages->findVisibleFor($user, $query),
            'trees' => array_map(fn (Organization $o): array => ['organization' => $o, 'pages' => $this->tree($pages, $o)], $organizations),
            'current' => $current,
        ];
    }
}
