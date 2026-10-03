<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\CalendarService;
use App\Calendar\Occurrence;
use App\Entity\CalendarItem;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Form\CalendarItemFormType;
use App\Notification\ActivityNotifier;
use App\Repository\MembershipRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\CalendarItemVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar')]
final class CalendarController extends AbstractController
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly EntityManagerInterface $em,
        private readonly ProjectRepository $projects,
        private readonly MembershipRepository $memberships,
        private readonly ActivityNotifier $notifier,
    ) {
    }

    /**
     * Start page: mini month (middle column) and the current week (detail). On mobile only the mini month.
     */
    #[Route('', name: 'calendar_month')]
    public function month(Request $request, #[CurrentUser] User $user): Response
    {
        $monday = new \DateTimeImmutable('monday this week')->setTime(0, 0);

        return $this->renderTimeline($request, $user, self::weekDays($monday), 'week', true);
    }

    #[Route('/week/{year<\d{4}>}/{week<\d{1,2}>}', name: 'calendar_week')]
    public function week(Request $request, int $year, int $week, #[CurrentUser] User $user): Response
    {
        $start = new \DateTimeImmutable()->setISODate($year, max(1, min(53, $week)))->setTime(0, 0);

        return $this->renderTimeline($request, $user, self::weekDays($start), 'week');
    }

    #[Route('/day/{date<\d{4}-\d{2}-\d{2}>}', name: 'calendar_day')]
    public function day(Request $request, string $date, #[CurrentUser] User $user): Response
    {
        $day = self::parseDate($date) ?? throw $this->createNotFoundException();

        return $this->renderTimeline($request, $user, [$day], 'day');
    }

    #[Route('/new', name: 'calendar_item_new')]
    public function new(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        $item = new CalendarItem($user);
        $date = self::parseDate($request->query->getString('date'));
        if (null !== $date) {
            $item->setStartsAt($date->setTime(9, 0));
        }
        if ('task' === $request->query->getString('type')) {
            $item->setType(CalendarItemType::Task);
        }
        $item->setOrganization($organizations->findForUser($user)[0] ?? null);
        $project = $this->projects->find($request->query->getInt('project'));
        if (null !== $project && $this->isGranted('PROJECT_VIEW', $project)) {
            $item->setProject($project)->setOrganization($project->getOrganization());
        }

        return $this->handleForm($request, $user, $item, $organizations);
    }

    #[Route('/item/{id<\d+>}', name: 'calendar_item_show')]
    #[IsGranted(CalendarItemVoter::EDIT, 'item')]
    public function show(Request $request, CalendarItem $item, #[CurrentUser] User $user): Response
    {
        $date = self::parseDate($request->query->getString('date'));

        return $this->render('calendar/show.html.twig', [
            'item' => $item,
            'occurrence_date' => $date,
        ] + $this->sidebarContext($request, $user, $date ?? $item->getStartsAt()));
    }

    #[Route('/item/{id<\d+>}/edit', name: 'calendar_item_edit')]
    #[IsGranted(CalendarItemVoter::EDIT, 'item')]
    public function edit(Request $request, CalendarItem $item, #[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        return $this->handleForm($request, $user, $item, $organizations);
    }

    #[Route('/item/{id<\d+>}/done', name: 'calendar_item_done', methods: ['POST'])]
    #[IsGranted(CalendarItemVoter::EDIT, 'item')]
    #[IsCsrfTokenValid('calendar-item')]
    public function done(Request $request, CalendarItem $item): Response
    {
        $item->setDone(!$item->isDone());
        $this->em->flush();

        return $this->redirect($this->safeReturnUrl($request) ?? $this->generateUrl('calendar_item_show', ['id' => $item->getId()]));
    }

    #[Route('/item/{id<\d+>}/delete', name: 'calendar_item_delete', methods: ['POST'])]
    #[IsGranted(CalendarItemVoter::EDIT, 'item')]
    #[IsCsrfTokenValid(new Expression('"delete-calendar-item-" ~ args["item"].getId()'))]
    public function delete(CalendarItem $item): Response
    {
        $month = $item->getStartsAt()->format('Y-m');
        $this->em->remove($item);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('calendar_month', ['month' => $month]);
    }

    private function handleForm(Request $request, User $user, CalendarItem $item, OrganizationRepository $organizations): Response
    {
        $isNew = null === $item->getId();
        $previousAssignees = $item->getAssignees()->toArray();
        $form = $this->createForm(CalendarItemFormType::class, $item, [
            'users' => $this->memberships->colleaguesOf($user),
            'organizations' => $organizations->findForUser($user),
            'projects' => $this->projects->findVisibleFor($user),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Projekt bestimmt die Organisation, damit alle Projektbeteiligten den Eintrag sehen
            if (null !== $item->getProject()) {
                $item->setOrganization($item->getProject()->getOrganization());
            }
            $this->em->persist($item);
            $this->em->flush();
            $this->notifier->calendarAssigned($item, array_filter($item->getAssignees()->toArray(), static fn (User $u): bool => !\in_array($u, $previousAssignees, true)), $user);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'calendar.created' : 'flash.saved');

            return $this->redirectToRoute('calendar_item_show', ['id' => $item->getId()]);
        }
        if ($form->isSubmitted() && !$isNew) {
            $this->em->refresh($item);
        }

        return $this->render('calendar/form.html.twig', ['form' => $form, 'item' => $isNew ? null : $item]
            + $this->sidebarContext($request, $user, $item->getStartsAt()));
    }

    /**
     * @param non-empty-list<\DateTimeImmutable> $days
     */
    private function renderTimeline(Request $request, User $user, array $days, string $mode, bool $isStart = false): Response
    {
        $project = $this->projectFilter($request);
        $last = $days[\count($days) - 1];
        $occurrences = $this->calendar->occurrences($user, $days[0], $last->modify('+1 day'), $project);
        $columns = [];
        foreach ($days as $day) {
            $allDay = [];
            $timed = [];
            foreach (CalendarService::forDay($occurrences, $day) as $occurrence) {
                if ($occurrence->isAllDayLike()) {
                    $allDay[] = $occurrence;
                } else {
                    $timed[] = ['o' => $occurrence, 'start' => $occurrence->startMinute($day), 'end' => $occurrence->endMinute($day)];
                }
            }
            $columns[] = ['date' => $day, 'all_day' => $allDay, 'timed' => self::layoutLanes($timed)];
        }

        return $this->render('calendar/timeline.html.twig', [
            'mode' => $mode,
            'is_start' => $isStart,
            'columns' => $columns,
            'first' => $days[0],
        ] + $this->sidebarContext($request, $user, $days[0]));
    }

    /**
     * Overlapping entries side by side: each gets a lane and the number of lanes of its overlap group.
     *
     * @param list<array{o: Occurrence, start: int, end: int}> $timed
     *
     * @return list<array{o: Occurrence, start: int, end: int, lane: int, lanes: int}>
     */
    private static function layoutLanes(array $timed): array
    {
        usort($timed, static fn (array $a, array $b): int => [$a['start'], $b['end']] <=> [$b['start'], $a['end']]);
        // Group index per entry; a new group starts when an entry begins after all previous ones ended
        $placed = [];
        $laneCount = [];
        $laneEnds = [];
        $groupEnd = -1;
        $groupIndex = -1;
        foreach ($timed as $entry) {
            if ($entry['start'] >= $groupEnd) {
                ++$groupIndex;
                $laneEnds = [];
                $groupEnd = -1;
            }
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $entry['start']) {
                ++$lane;
            }
            $laneEnds[$lane] = $entry['end'];
            $groupEnd = max($groupEnd, $entry['end']);
            $laneCount[$groupIndex] = \count($laneEnds);
            $placed[] = [$groupIndex, $entry + ['lane' => $lane]];
        }

        return array_map(static fn (array $p): array => $p[1] + ['lanes' => $laneCount[$p[0]]], $placed);
    }

    /**
     * Middle column of all calendar pages: mini month (from ?month= or the shown date) and upcoming entries.
     *
     * @return array<string, mixed>
     */
    private function sidebarContext(Request $request, User $user, \DateTimeImmutable $reference): array
    {
        $project = $this->projectFilter($request);
        $month = self::parseDate($request->query->getString('month').'-01') ?? $reference;
        $month = $month->modify('first day of this month')->setTime(0, 0);
        $gridStart = $month->modify('monday this week');
        $gridEnd = $month->modify('last day of this month')->modify('sunday this week')->modify('+1 day');

        $occurrences = $this->calendar->occurrences($user, $gridStart, $gridEnd, $project);
        $weeks = [];
        for ($day = $gridStart; $day < $gridEnd; $day = $day->modify('+1 day')) {
            $key = $day->format('o-W');
            $weeks[$key] ??= ['year' => (int) $day->format('o'), 'number' => (int) $day->format('W'), 'days' => []];
            $dayOccurrences = CalendarService::forDay($occurrences, $day);
            $weeks[$key]['days'][] = [
                'date' => $day,
                'in_month' => $day->format('m') === $month->format('m'),
                'count' => \count($dayOccurrences),
                'colors' => \array_slice(array_values(array_unique(array_map(static fn (Occurrence $o): string => $o->item->getColor(), $dayOccurrences))), 0, 3),
            ];
        }

        $now = new \DateTimeImmutable();
        $upcoming = array_values(array_filter(
            $this->calendar->occurrences($user, $now->setTime(0, 0), $now->modify('+30 days'), $project),
            static fn (Occurrence $o): bool => !$o->item->isDone() && $o->end >= $now,
        ));

        return [
            'mini_month' => $month,
            'mini_weeks' => array_values($weeks),
            'upcoming' => \array_slice($upcoming, 0, 8),
            'project_filter' => $project,
            'projects' => $this->projects->findVisibleFor($user),
        ];
    }

    /**
     * @return non-empty-list<\DateTimeImmutable>
     */
    private static function weekDays(\DateTimeImmutable $monday): array
    {
        $days = [$monday];
        for ($i = 1; $i < 7; ++$i) {
            $days[] = $monday->modify("+$i days");
        }

        return $days;
    }

    private function projectFilter(Request $request): ?int
    {
        return $request->query->getInt('project') ?: null;
    }

    private function safeReturnUrl(Request $request): ?string
    {
        $url = $request->getPayload()->getString('return');

        return str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : null;
    }

    private static function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
