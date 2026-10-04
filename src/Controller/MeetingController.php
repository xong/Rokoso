<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AgendaItem;
use App\Entity\Attendance;
use App\Entity\Meeting;
use App\Entity\User;
use App\Enum\AttendanceStatus;
use App\Form\AgendaItemFormType;
use App\Form\MeetingFormType;
use App\Meeting\MeetingService;
use App\Repository\MeetingRepository;
use App\Repository\MembershipRepository;
use App\Repository\OrganizationRepository;
use App\Repository\PollRepository;
use App\Repository\ProjectRepository;
use App\Repository\StoredFileRepository;
use App\Security\Voter\MeetingVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Committee meetings: agenda, invitation, attendance, minutes.
 */
#[Route('/meetings')]
final class MeetingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MeetingRepository $meetings,
        private readonly MeetingService $service,
        private readonly ProjectRepository $projects,
    ) {
    }

    #[Route('', name: 'meeting_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render('meeting/index.html.twig', $this->listContext($request, $user));
    }

    #[Route('/new', name: 'meeting_new')]
    public function new(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations, MembershipRepository $memberships): Response
    {
        $choices = $organizations->findForUser($user);
        if ([] === $choices) {
            $this->addFlash('error', 'meeting.no_organization');

            return $this->redirectToRoute('meeting_index');
        }
        $organization = $organizations->find($request->query->getInt('organization'));
        $meeting = new Meeting(\in_array($organization, $choices, true) ? $organization : $choices[0], $user);
        $project = $this->projects->find($request->query->getInt('project'));
        if (null !== $project && $this->isGranted('PROJECT_VIEW', $project)) {
            $meeting->setProject($project);
            if (null !== $project->getOrganization() && \in_array($project->getOrganization(), $choices, true)) {
                $meeting->setOrganization($project->getOrganization());
            }
        }

        return $this->handleForm($request, $user, $meeting, ['organizations' => $choices, 'users' => $memberships->colleaguesOf($user)]);
    }

    #[Route('/{id<\d+>}', name: 'meeting_show')]
    #[IsGranted(MeetingVoter::VIEW, 'meeting')]
    public function show(Request $request, Meeting $meeting, #[CurrentUser] User $user, PollRepository $polls): Response
    {
        return $this->render('meeting/show.html.twig', [
            'meeting' => $meeting,
            'polls' => $polls->forMeeting($meeting),
            'can_manage' => $this->isGranted(MeetingVoter::MANAGE, $meeting),
        ] + $this->listContext($request, $user, $meeting));
    }

    #[Route('/{id<\d+>}/edit', name: 'meeting_edit')]
    #[IsGranted(MeetingVoter::MANAGE, 'meeting')]
    public function edit(Request $request, Meeting $meeting, #[CurrentUser] User $user): Response
    {
        return $this->handleForm($request, $user, $meeting, [
            'organizations' => [$meeting->getOrganization()],
            'users' => $meeting->getOrganization()->getMembers(),
            'lock_organization' => true,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'meeting_delete', methods: ['POST'])]
    #[IsGranted(MeetingVoter::MANAGE, 'meeting')]
    #[IsCsrfTokenValid('meeting-delete')]
    public function delete(Meeting $meeting): Response
    {
        $this->em->remove($meeting);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('meeting_index');
    }

    /**
     * Sends the invitation with agenda and .ics to all members and guests.
     */
    #[Route('/{id<\d+>}/invite', name: 'meeting_invite', methods: ['POST'])]
    #[IsGranted(MeetingVoter::MANAGE, 'meeting')]
    #[IsCsrfTokenValid('meeting-invite')]
    public function invite(Meeting $meeting, #[CurrentUser] User $user, TranslatorInterface $translator): Response
    {
        $count = $this->service->invite($meeting, $user);
        $this->em->flush();
        $this->addFlash('success', $translator->trans('meeting.invited_flash', ['%count%' => $count]));

        return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId()]);
    }

    #[Route('/{id<\d+>}/meeting.ics', name: 'meeting_ics')]
    #[IsGranted(MeetingVoter::VIEW, 'meeting')]
    public function ics(Meeting $meeting): Response
    {
        $url = $this->generateUrl('meeting_show', ['id' => $meeting->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new Response($this->service->ics($meeting, $url), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="sitzung-'.$meeting->getId().'.ics"',
        ]);
    }

    /**
     * Attendance list: one status per member.
     */
    #[Route('/{id<\d+>}/attendance', name: 'meeting_attendance')]
    #[IsGranted(MeetingVoter::MANAGE, 'meeting')]
    public function attendance(Request $request, Meeting $meeting, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            $this->assertToken($request, 'meeting-attendance');
            $input = $request->getPayload()->all('attendance');
            foreach ($meeting->getOrganization()->getMembers() as $member) {
                $status = AttendanceStatus::tryFrom((string) ($input[$member->getId() ?? 0] ?? ''));
                $attendance = $meeting->getAttendance($member);
                if (null === $status) {
                    if (null !== $attendance) {
                        $meeting->removeAttendance($attendance);
                    }
                } elseif (null === $attendance) {
                    $meeting->addAttendance(new Attendance($meeting, $member, $status));
                } else {
                    $attendance->setStatus($status);
                }
            }
            $meeting->markHeld();
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId()]);
        }

        return $this->render('meeting/attendance.html.twig', [
            'meeting' => $meeting,
            'statuses' => AttendanceStatus::cases(),
        ] + $this->listContext($request, $user, $meeting));
    }

    /**
     * Minutes: text per agenda item plus general notes. Locked once approved.
     */
    #[Route('/{id<\d+>}/minutes', name: 'meeting_minutes')]
    #[IsGranted(MeetingVoter::MANAGE, 'meeting')]
    public function minutes(Request $request, Meeting $meeting, #[CurrentUser] User $user): Response
    {
        if ($meeting->isMinutesApproved()) {
            $this->addFlash('error', 'meeting.minutes_locked');

            return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId()]);
        }
        if ($request->isMethod('POST')) {
            $this->assertToken($request, 'meeting-minutes');
            $input = $request->getPayload()->all('minutes');
            foreach ($meeting->getAgenda() as $item) {
                $text = trim((string) ($input[$item->getId() ?? 0] ?? ''));
                $item->setMinutes('' === $text ? null : mb_substr($text, 0, 50000));
            }
            $notes = trim($request->getPayload()->getString('notes'));
            $meeting->setMinutesNotes('' === $notes ? null : mb_substr($notes, 0, 20000));
            $meeting->markHeld();
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId()]);
        }

        return $this->render('meeting/minutes.html.twig', ['meeting' => $meeting] + $this->listContext($request, $user, $meeting));
    }

    #[Route('/{id<\d+>}/minutes/approve', name: 'meeting_approve', methods: ['POST'])]
    #[IsGranted(MeetingVoter::MANAGE, 'meeting')]
    #[IsCsrfTokenValid('meeting-approve')]
    public function approve(Meeting $meeting, #[CurrentUser] User $user): Response
    {
        if ($meeting->isMinutesApproved()) {
            $meeting->reopenMinutes();
        } else {
            $meeting->approveMinutes($user);
        }
        $this->em->flush();
        $this->addFlash('success', 'flash.saved');

        return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId()]);
    }

    /** Printable agenda and minutes (standalone page without navigation). */
    #[Route('/{id<\d+>}/print', name: 'meeting_print')]
    #[IsGranted(MeetingVoter::VIEW, 'meeting')]
    public function print(Meeting $meeting): Response
    {
        return $this->render('meeting/print.html.twig', ['meeting' => $meeting]);
    }

    /**
     * New agenda item; members without management rights submit a proposal.
     */
    #[Route('/{id<\d+>}/agenda/new', name: 'meeting_agenda_new')]
    #[IsGranted(MeetingVoter::VIEW, 'meeting')]
    public function agendaNew(Request $request, Meeting $meeting, #[CurrentUser] User $user, StoredFileRepository $files): Response
    {
        $item = new AgendaItem($meeting);
        if (!$this->isGranted(MeetingVoter::MANAGE, $meeting)) {
            $item->propose($user);
        }

        return $this->handleAgendaForm($request, $user, $item, $files);
    }

    #[Route('/agenda/{id<\d+>}/edit', name: 'meeting_agenda_edit')]
    #[IsGranted(MeetingVoter::MANAGE, 'item')]
    public function agendaEdit(Request $request, AgendaItem $item, #[CurrentUser] User $user, StoredFileRepository $files): Response
    {
        return $this->handleAgendaForm($request, $user, $item, $files);
    }

    #[Route('/agenda/{id<\d+>}/delete', name: 'meeting_agenda_delete', methods: ['POST'])]
    #[IsGranted(MeetingVoter::MANAGE, 'item')]
    #[IsCsrfTokenValid('meeting-agenda')]
    public function agendaDelete(AgendaItem $item): Response
    {
        $meeting = $item->getMeeting();
        $meeting->removeAgendaItem($item);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId(), '_fragment' => 'agenda']);
    }

    /** Moves an accepted agenda item one place up or down. */
    #[Route('/agenda/{id<\d+>}/move', name: 'meeting_agenda_move', methods: ['POST'])]
    #[IsGranted(MeetingVoter::MANAGE, 'item')]
    #[IsCsrfTokenValid('meeting-agenda')]
    public function agendaMove(Request $request, AgendaItem $item): Response
    {
        $meeting = $item->getMeeting();
        $agenda = $meeting->getAgenda();
        $index = array_search($item, $agenda, true);
        $target = false === $index ? null : ($agenda[$index + ('up' === $request->getPayload()->getString('direction') ? -1 : 1)] ?? null);
        if (null !== $target) {
            $position = $item->getPosition();
            $item->setPosition($target->getPosition());
            $target->setPosition($position);
            $this->em->flush();
        }

        return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId(), '_fragment' => 'agenda-'.$item->getId()]);
    }

    #[Route('/agenda/{id<\d+>}/accept', name: 'meeting_agenda_accept', methods: ['POST'])]
    #[IsGranted(MeetingVoter::MANAGE, 'item')]
    #[IsCsrfTokenValid('meeting-agenda')]
    public function agendaAccept(AgendaItem $item): Response
    {
        $item->accept()->setPosition($item->getMeeting()->nextPosition());
        $this->em->flush();
        $this->addFlash('success', 'meeting.agenda.accepted');

        return $this->redirectToRoute('meeting_show', ['id' => $item->getMeeting()->getId(), '_fragment' => 'agenda']);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function handleForm(Request $request, User $user, Meeting $meeting, array $options): Response
    {
        $isNew = null === $meeting->getId();
        $form = $this->createForm(MeetingFormType::class, $meeting, $options + ['projects' => $this->projects->findVisibleFor($user, $meeting->getProject())]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // The minute taker must belong to the organization
            if (null !== $meeting->getMinuteTaker() && null === $meeting->getOrganization()->getMembership($meeting->getMinuteTaker())) {
                $meeting->setMinuteTaker(null);
            }
            if (null !== $meeting->getProject() && null !== $meeting->getProject()->getOrganization() && $meeting->getProject()->getOrganization() !== $meeting->getOrganization()) {
                $meeting->setProject(null);
            }
            if ($isNew) {
                $this->em->persist($meeting);
            }
            $this->service->syncCalendar($meeting);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId()]);
        }

        return $this->render('meeting/form.html.twig', [
            'form' => $form,
            'meeting' => $isNew ? null : $meeting,
        ] + $this->listContext($request, $user, $isNew ? null : $meeting));
    }

    private function handleAgendaForm(Request $request, User $user, AgendaItem $item, StoredFileRepository $files): Response
    {
        $meeting = $item->getMeeting();
        $isNew = null === $item->getId();
        $form = $this->createForm(AgendaItemFormType::class, $item, [
            'proposal' => $item->isProposed() && $isNew,
            'users' => $meeting->getOrganization()->getMembers(),
            'files' => $files->forOrganization($meeting->getOrganization()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $meeting->addAgendaItem($item);
                $this->em->persist($item);
            }
            $this->em->flush();
            $this->addFlash('success', $item->isProposed() ? 'meeting.agenda.proposed_flash' : 'flash.saved');

            return $this->redirectToRoute('meeting_show', ['id' => $meeting->getId(), '_fragment' => 'agenda']);
        }

        return $this->render('meeting/agenda_form.html.twig', [
            'form' => $form,
            'meeting' => $meeting,
            'item' => $isNew ? null : $item,
            'proposal' => $item->isProposed() && $isNew,
        ] + $this->listContext($request, $user, $meeting));
    }

    /**
     * Middle column: upcoming or past meetings.
     *
     * @return array<string, mixed>
     */
    private function listContext(Request $request, User $user, ?Meeting $current = null): array
    {
        $past = $request->query->getBoolean('past', null !== $current && $current->getStartsAt() < new \DateTimeImmutable('today'));

        return [
            'meetings' => $this->meetings->findForUser($user, $past, limit: 100),
            'past' => $past,
            'current' => $current,
        ];
    }

    private function assertToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
