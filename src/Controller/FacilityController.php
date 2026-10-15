<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Facility;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\FacilityRole;
use App\Enum\Feature;
use App\Facility\FacilityPerson;
use App\Facility\FacilitySeats;
use App\Form\FacilityFormType;
use App\Form\FacilityPersonFormType;
use App\Repository\FacilityRepository;
use App\Repository\OrganizationRepository;
use App\Security\Voter\FacilityVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Facilities with their representative and deputy (people are contacts), for invitations and circulars.
 */
#[Route('/contacts/facilities')]
final class FacilityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FacilityRepository $facilities,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    #[Route('', name: 'facility_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $context = $this->listContext($request, $user);

        // overview and circulars per organization (all facilities, the district filter narrows the circulars)
        $overview = [];
        foreach ($this->facilities->search($user) as $facility) {
            $organization = $facility->getOrganization();
            $entry = $overview[spl_object_id($organization)] ?? ['organization' => $organization, 'total' => 0, 'represented' => 0, 'missing' => 0, 'no_deputy' => 0, 'review' => 0];
            ++$entry['total'];
            null === $facility->getRepresentative() ? ++$entry['missing'] : ++$entry['represented'];
            if (null === $facility->getDeputy()) {
                ++$entry['no_deputy'];
            }
            if ($facility->needsReview()) {
                ++$entry['review'];
            }
            $overview[spl_object_id($organization)] = $entry;
        }
        foreach ($overview as $id => $entry) {
            foreach ([FacilityRepository::TO_REPRESENTATIVES, FacilityRepository::TO_ALL, FacilityRepository::TO_MISSING] as $recipients) {
                $overview[$id]['recipients'][$recipients] = \count($this->facilities->addresses($entry['organization'], $recipients, $context['district']));
            }
        }

        return $this->render('facility/index.html.twig', $context + ['overview' => array_values($overview)]);
    }

    #[Route('/new', name: 'facility_new')]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $organizations = $this->organizationsFor($user);
        $organization = $organizations[0] ?? null;
        foreach ($organizations as $candidate) {
            if ($candidate->getId() === $request->query->getInt('organization')) {
                $organization = $candidate;
            }
        }
        if (null === $organization) {
            throw $this->createAccessDeniedException();
        }

        return $this->handleForm($request, $user, new Facility($organization), $organizations);
    }

    #[Route('/{id<\d+>}', name: 'facility_show')]
    #[IsGranted(FacilityVoter::EDIT, 'facility')]
    public function show(Request $request, Facility $facility, #[CurrentUser] User $user): Response
    {
        return $this->render('facility/show.html.twig', $this->listContext($request, $user) + ['facility' => $facility]);
    }

    #[Route('/{id<\d+>}/edit', name: 'facility_edit')]
    #[IsGranted(FacilityVoter::EDIT, 'facility')]
    public function edit(Request $request, Facility $facility, #[CurrentUser] User $user): Response
    {
        return $this->handleForm($request, $user, $facility, []);
    }

    /** Deletes the facility; its people stay contacts. */
    #[Route('/{id<\d+>}/delete', name: 'facility_delete', methods: ['POST'])]
    #[IsGranted(FacilityVoter::EDIT, 'facility')]
    #[IsCsrfTokenValid(new Expression('"delete-facility-" ~ args["facility"].getId()'))]
    public function delete(Facility $facility): Response
    {
        $this->em->remove($facility);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('facility_index');
    }

    /** Enters a newly reported person (replaces whoever held the seat). */
    #[Route('/{id<\d+>}/{role}', name: 'facility_assign', requirements: ['role' => 'representative|deputy'])]
    #[IsGranted(FacilityVoter::EDIT, 'facility')]
    public function assign(Request $request, Facility $facility, FacilityRole $role, FacilitySeats $seats, #[CurrentUser] User $user): Response
    {
        $previous = $facility->getPerson($role);
        $deletable = null !== $previous && $seats->isOnlySeat($previous, $facility);
        $person = new FacilityPerson();
        $form = $this->createForm(FacilityPersonFormType::class, $person, ['previous' => $deletable ? $previous->getDisplayName() : null]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $seats->assign($facility, $role, $person, $user);
            $this->addFlash('success', 'facility.person_saved');

            return $this->redirectToRoute('facility_show', ['id' => $facility->getId()]);
        }

        return $this->render('facility/person.html.twig', $this->listContext($request, $user) + [
            'facility' => $facility,
            'role' => $role,
            'previous' => $previous,
            'previous_deletable' => $deletable,
            'form' => $form,
        ]);
    }

    /** Leaves the seat empty (e.g. the person stepped down), optionally deleting the contact. */
    #[Route('/{id<\d+>}/{role}/release', name: 'facility_release', requirements: ['role' => 'representative|deputy'], methods: ['POST'])]
    #[IsGranted(FacilityVoter::EDIT, 'facility')]
    #[IsCsrfTokenValid(new Expression('"release-facility-" ~ args["facility"].getId()'))]
    public function release(Request $request, Facility $facility, FacilityRole $role, FacilitySeats $seats): Response
    {
        $seats->release($facility, $role, $request->getPayload()->getBoolean('delete_contact'));
        $this->addFlash('success', 'facility.person_released');

        return $this->redirectToRoute('facility_show', ['id' => $facility->getId()]);
    }

    /**
     * @param list<Organization> $organizations choices when creating (empty when editing)
     */
    private function handleForm(Request $request, User $user, Facility $facility, array $organizations): Response
    {
        $isNew = null === $facility->getId();
        $form = $this->createForm(FacilityFormType::class, $facility);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($facility);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'facility.created' : 'flash.saved');

            return $this->redirectToRoute('facility_show', ['id' => $facility->getId()]);
        }
        if ($form->isSubmitted() && !$isNew) {
            $this->em->refresh($facility);
        }

        return $this->render('facility/form.html.twig', $this->listContext($request, $user) + [
            'form' => $form,
            'edited' => $isNew ? null : $facility,
            'organization' => $facility->getOrganization(),
            'organizations' => $organizations,
            'carriers' => $this->facilities->distinctValues($user, 'carrier'),
        ]);
    }

    /**
     * Organizations where the user may keep facilities (area and contacts switched on).
     *
     * @return list<Organization>
     */
    private function organizationsFor(User $user): array
    {
        return array_values(array_filter(
            $this->organizations->findForUser($user, Feature::Facilities),
            static fn (Organization $o): bool => $o->hasFeature(Feature::Contacts),
        ));
    }

    /**
     * Facility list (search, status and district filter) for the list column.
     *
     * @return array{facilities: list<Facility>, query: string, status: ?string, district: ?string, districts: list<string>, can_create: bool}
     */
    private function listContext(Request $request, User $user): array
    {
        $query = trim($request->query->getString('q'));
        $status = $request->query->getString('status');
        $status = \in_array($status, [FacilityRepository::MISSING, FacilityRepository::NO_DEPUTY, FacilityRepository::REVIEW], true) ? $status : null;
        $districts = $this->facilities->distinctValues($user, 'district');
        $district = $request->query->getString('district');
        $district = \in_array($district, $districts, true) ? $district : null;

        return [
            'facilities' => $this->facilities->search($user, $query, $status, $district),
            'query' => $query,
            'status' => $status,
            'district' => $district,
            'districts' => $districts,
            'can_create' => [] !== $this->organizationsFor($user),
        ];
    }
}
