<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AgendaItem;
use App\Entity\ForumTopic;
use App\Entity\Meeting;
use App\Entity\Poll;
use App\Entity\PollAnswer;
use App\Entity\User;
use App\Enum\PollKind;
use App\Form\PollFormType;
use App\Poll\PollService;
use App\Repository\OrganizationRepository;
use App\Repository\PollRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\ForumVoter;
use App\Security\Voter\MeetingVoter;
use App\Security\Voter\PollVoter;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Votes, circular resolutions and date finding.
 */
#[Route('/polls')]
final class PollController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PollRepository $polls,
        private readonly PollService $service,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'poll_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render('poll/index.html.twig', $this->listContext($request, $user));
    }

    /**
     * New poll, optionally attached to a forum topic (?topic=), agenda item (?agenda=), meeting (?meeting=) or project (?project=).
     */
    #[Route('/new', name: 'poll_new')]
    public function new(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations, ProjectRepository $projects): Response
    {
        $choices = $organizations->findForUser($user);
        if ([] === $choices) {
            $this->addFlash('error', 'poll.no_organization');

            return $this->redirectToRoute('poll_index');
        }
        $poll = new Poll($choices[0], $user);
        $poll->setKind(PollKind::tryFrom($request->query->getString('kind')) ?? PollKind::Decision);
        $lock = false;

        $topic = $this->em->find(ForumTopic::class, $request->query->getInt('topic'));
        $agendaItem = $this->em->find(AgendaItem::class, $request->query->getInt('agenda'));
        $meeting = $this->em->find(Meeting::class, $request->query->getInt('meeting'));
        $project = $projects->find($request->query->getInt('project'));
        if (null !== $topic && $this->isGranted(ForumVoter::VIEW, $topic)) {
            $poll->setTopic($topic)->setOrganization($topic->getOrganization())->setProject($topic->getProject())->setTitle($topic->getTitle());
            $lock = true;
        } elseif (null !== $agendaItem && $this->isGranted(MeetingVoter::VIEW, $agendaItem->getMeeting())) {
            $poll->setAgendaItem($agendaItem)->setOrganization($agendaItem->getMeeting()->getOrganization())
                ->setProject($agendaItem->getMeeting()->getProject())->setTitle($agendaItem->getTitle());
            $lock = true;
        } elseif (null !== $meeting && $this->isGranted(MeetingVoter::VIEW, $meeting)) {
            $poll->setMeeting($meeting)->setOrganization($meeting->getOrganization())->setProject($meeting->getProject())
                ->setTitle($this->translator->trans('poll.schedule_title', ['%title%' => $meeting->getTitle()]));
            $lock = true;
        } elseif (null !== $project && $this->isGranted(ProjectVoter::VIEW, $project)) {
            $poll->setProject($project);
            if (null !== $project->getOrganization() && \in_array($project->getOrganization(), $choices, true)) {
                $poll->setOrganization($project->getOrganization());
            }
        }

        $form = $this->createForm(PollFormType::class, $poll, [
            'organizations' => $lock ? [$poll->getOrganization()] : $choices,
            'projects' => $projects->findVisibleFor($user),
            'lock_organization' => $lock,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            [$labels, $slots] = $this->readOptions($form, $poll);
            if (PollKind::Choice === $poll->getKind() && \count($labels) < 2) {
                $form->get('options')->addError(new FormError($this->translator->trans('poll.options_min')));
            } elseif (PollKind::Schedule === $poll->getKind() && [] === $slots) {
                $form->get('slot1')->addError(new FormError($this->translator->trans('poll.slots_min')));
            } else {
                if (null !== $poll->getProject() && null !== $poll->getProject()->getOrganization() && $poll->getProject()->getOrganization() !== $poll->getOrganization()) {
                    $poll->setProject(null);
                }
                $this->service->create($poll, $labels, $slots, $user);
                $this->em->flush();
                $this->addFlash('success', 'poll.created_flash');

                return $this->redirectToRoute('poll_show', ['id' => $poll->getId()]);
            }
        }

        return $this->render('poll/form.html.twig', ['form' => $form, 'poll' => $poll] + $this->listContext($request, $user));
    }

    #[Route('/{id<\d+>}', name: 'poll_show')]
    #[IsGranted(PollVoter::VIEW, 'poll')]
    public function show(Request $request, Poll $poll, #[CurrentUser] User $user): Response
    {
        $canManage = $this->isGranted(PollVoter::MANAGE, $poll);

        return $this->render('poll/show.html.twig', [
            'poll' => $poll,
            'can_vote' => $this->isGranted(PollVoter::VOTE, $poll),
            'can_manage' => $canManage,
            'show_results' => $poll->isEnded() || (!$poll->isSecret() && ($canManage || $poll->hasVoted($user))),
        ] + $this->listContext($request, $user, $poll));
    }

    /**
     * Payload: choice[] = option ids (decision/choice) or slot[optionId] = 1 (yes) | 2 (maybe) | 0 (no).
     */
    #[Route('/{id<\d+>}/vote', name: 'poll_vote', methods: ['POST'])]
    #[IsGranted(PollVoter::VOTE, 'poll')]
    #[IsCsrfTokenValid('poll-vote')]
    public function vote(Request $request, Poll $poll, #[CurrentUser] User $user): Response
    {
        $payload = $request->getPayload();
        $answers = [];
        if (PollKind::Schedule === $poll->getKind()) {
            foreach ($payload->all('slot') as $id => $value) {
                $answers[(int) $id] = is_numeric($value) ? (int) $value : 0;
            }
        } else {
            foreach ($payload->all('choice') as $id) {
                if (is_numeric($id)) {
                    $answers[(int) $id] = PollAnswer::YES;
                }
            }
        }
        if ($this->service->vote($poll, $user, $answers)) {
            $this->em->flush();
            $this->addFlash('success', 'poll.voted_flash');
        } else {
            $this->addFlash('error', $poll->isMultiple() ? 'poll.vote_invalid' : 'poll.vote_invalid_single');
        }

        return $this->redirectToRoute('poll_show', ['id' => $poll->getId()]);
    }

    /**
     * Ends the poll; for date finding with the chosen slot (option).
     */
    #[Route('/{id<\d+>}/close', name: 'poll_close', methods: ['POST'])]
    #[IsGranted(PollVoter::MANAGE, 'poll')]
    #[IsCsrfTokenValid('poll-close')]
    public function close(Request $request, Poll $poll, #[CurrentUser] User $user): Response
    {
        $option = $request->getPayload()->getString('option');
        $chosen = ctype_digit($option) ? $poll->getOption((int) $option) : null;
        $this->service->close($poll, $user, $chosen);
        $this->em->flush();
        $this->addFlash('success', null !== $poll->getResolution()
            ? $this->translator->trans('poll.closed_resolution_flash', ['%number%' => $poll->getResolution()->getNumber()])
            : (null !== $poll->getCalendarItem() ? 'poll.closed_date_flash' : 'poll.closed_flash'));

        return $this->redirectToRoute('poll_show', ['id' => $poll->getId()]);
    }

    #[Route('/{id<\d+>}/reopen', name: 'poll_reopen', methods: ['POST'])]
    #[IsGranted(PollVoter::MANAGE, 'poll')]
    #[IsCsrfTokenValid('poll-reopen')]
    public function reopen(Poll $poll): Response
    {
        if (null === $poll->getResolution() && null === $poll->getCalendarItem()) {
            $poll->reopen();
            if (null !== $poll->getDeadline() && $poll->getDeadline() <= new \DateTimeImmutable()) {
                $poll->setDeadline(null);
            }
            $this->em->flush();
            $this->addFlash('success', 'poll.reopened_flash');
        }

        return $this->redirectToRoute('poll_show', ['id' => $poll->getId()]);
    }

    #[Route('/{id<\d+>}/delete', name: 'poll_delete', methods: ['POST'])]
    #[IsGranted(PollVoter::MANAGE, 'poll')]
    #[IsCsrfTokenValid('poll-delete')]
    public function delete(Poll $poll): Response
    {
        $this->em->remove($poll);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('poll_index');
    }

    /**
     * @param FormInterface<Poll> $form
     *
     * @return array{0: list<string>, 1: list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable|null}>}
     */
    private function readOptions(FormInterface $form, Poll $poll): array
    {
        $labels = [];
        $slots = [];
        if (PollKind::Choice === $poll->getKind()) {
            $text = $form->get('options')->getData();
            $lines = preg_split('/\R/', \is_string($text) ? $text : '') ?: [];
            $labels = array_values(array_unique(array_filter(array_map('trim', $lines), static fn (string $l): bool => '' !== $l)));
        }
        if (PollKind::Schedule === $poll->getKind()) {
            $minutes = $form->get('slotMinutes')->getData();
            for ($i = 1; $i <= PollFormType::SLOTS; ++$i) {
                $start = $form->get('slot'.$i)->getData();
                if ($start instanceof \DateTimeImmutable) {
                    $slots[] = [$start, \is_int($minutes) && $minutes > 0 ? $start->modify('+'.$minutes.' minutes') : null];
                }
            }
            usort($slots, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        }

        return [$labels, $slots];
    }

    /**
     * @return array<string, mixed>
     */
    private function listContext(Request $request, User $user, ?Poll $current = null): array
    {
        $ended = $request->query->getBoolean('ended', null !== $current && $current->isEnded());

        return [
            'polls' => $this->polls->findForUser($user, $ended, 100),
            'ended' => $ended,
            'current' => $current,
        ];
    }
}
