<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AgendaItem;
use App\Entity\Resolution;
use App\Entity\User;
use App\Form\ResolutionFormType;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use App\Repository\ResolutionRepository;
use App\Security\Voter\MeetingVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Resolution register: searchable list of all resolutions of the user's organizations.
 */
#[Route('/resolutions')]
final class ResolutionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResolutionRepository $resolutions,
        private readonly ProjectRepository $projects,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    #[Route('', name: 'resolution_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render('resolution/index.html.twig', $this->listContext($request, $user));
    }

    #[Route('/{id<\d+>}', name: 'resolution_show')]
    #[IsGranted(MeetingVoter::VIEW, 'resolution')]
    public function show(Request $request, Resolution $resolution, #[CurrentUser] User $user): Response
    {
        return $this->render('resolution/show.html.twig', [
            'resolution' => $resolution,
            'can_manage' => $this->isGranted(MeetingVoter::MANAGE, $resolution),
        ] + $this->listContext($request, $user, $resolution));
    }

    /**
     * New resolution, either under an agenda item (?agenda=) or standalone for an organization.
     */
    #[Route('/new', name: 'resolution_new')]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $item = $this->em->find(AgendaItem::class, $request->query->getInt('agenda'));
        if (null !== $item) {
            $this->denyAccessUnlessGranted(MeetingVoter::MANAGE, $item);
            $resolution = new Resolution($item->getOrganization(), $user);
            $resolution->setAgendaItem($item)->setTitle($item->getTitle())->setProject($item->getMeeting()->getProject())
                ->setDecidedOn($item->getMeeting()->getStartsAt()->setTime(0, 0));
        } else {
            $choices = $this->organizations->findForUser($user);
            $organization = $this->organizations->find($request->query->getInt('organization'));
            $organization = \in_array($organization, $choices, true) ? $organization : ($choices[0] ?? null);
            if (null === $organization) {
                throw $this->createAccessDeniedException();
            }
            $resolution = new Resolution($organization, $user);
        }

        return $this->handleForm($request, $user, $resolution);
    }

    #[Route('/{id<\d+>}/edit', name: 'resolution_edit')]
    #[IsGranted(MeetingVoter::MANAGE, 'resolution')]
    public function edit(Request $request, Resolution $resolution, #[CurrentUser] User $user): Response
    {
        return $this->handleForm($request, $user, $resolution);
    }

    #[Route('/{id<\d+>}/delete', name: 'resolution_delete', methods: ['POST'])]
    #[IsGranted(MeetingVoter::MANAGE, 'resolution')]
    #[IsCsrfTokenValid('resolution-delete')]
    public function delete(Resolution $resolution): Response
    {
        $meeting = $resolution->getMeeting();
        $this->em->remove($resolution);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return null === $meeting
            ? $this->redirectToRoute('resolution_index')
            : $this->redirectToRoute('meeting_show', ['id' => $meeting->getId(), '_fragment' => 'agenda']);
    }

    private function handleForm(Request $request, User $user, Resolution $resolution): Response
    {
        $isNew = null === $resolution->getId();
        $projects = array_values(array_filter($this->projects->findVisibleFor($user),
            static fn ($p): bool => null === $p->getOrganization() || $p->getOrganization() === $resolution->getOrganization()));
        $form = $this->createForm(ResolutionFormType::class, $resolution, ['projects' => $projects]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $resolution->setNumber($this->resolutions->nextNumber($resolution->getOrganization(), (int) $resolution->getDecidedOn()->format('Y')));
                $this->em->persist($resolution);
            }
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('resolution_show', ['id' => $resolution->getId()]);
        }

        return $this->render('resolution/form.html.twig', [
            'form' => $form,
            'resolution' => $resolution,
            'is_new' => $isNew,
        ] + $this->listContext($request, $user, $isNew ? null : $resolution));
    }

    /**
     * Middle column with filters (search text, year, project, organization).
     *
     * @return array<string, mixed>
     */
    private function listContext(Request $request, User $user, ?Resolution $current = null): array
    {
        $filters = [
            'q' => trim($request->query->getString('q')),
            'year' => $request->query->getInt('year') ?: null,
            'project' => $request->query->getInt('project') ?: null,
            'organization' => $request->query->getInt('organization') ?: null,
        ];

        return [
            'resolutions' => $this->resolutions->search($user, $filters['q'], $filters['year'], $filters['project'], $filters['organization']),
            'filters' => $filters,
            'filter_params' => array_filter($filters),
            'years' => $this->resolutions->years($user),
            'projects' => $this->projects->findVisibleFor($user),
            'organizations' => $this->organizations->findForUser($user),
            'current' => $current,
        ];
    }
}
