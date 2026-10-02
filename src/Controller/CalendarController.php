<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\CalendarService;
use App\Entity\CalendarItem;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Form\CalendarItemFormType;
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
    ) {
    }

    #[Route('', name: 'calendar_month')]
    public function month(Request $request, #[CurrentUser] User $user): Response
    {
        $month = self::parseDate($request->query->getString('month').'-01') ?? new \DateTimeImmutable('first day of this month');
        $month = $month->modify('first day of this month')->setTime(0, 0);
        $gridStart = $month->modify('monday this week');
        $gridEnd = $month->modify('last day of this month')->modify('sunday this week')->modify('+1 day');
        $project = $this->projectFilter($request);

        $occurrences = $this->calendar->occurrences($user, $gridStart, $gridEnd, $project);
        $weeks = [];
        for ($day = $gridStart; $day < $gridEnd; $day = $day->modify('+1 day')) {
            $key = $day->format('o-W');
            $weeks[$key] ??= ['year' => (int) $day->format('o'), 'number' => (int) $day->format('W'), 'days' => []];
            $weeks[$key]['days'][] = [
                'date' => $day,
                'in_month' => $day->format('m') === $month->format('m'),
                'occurrences' => CalendarService::forDay($occurrences, $day),
            ];
        }

        return $this->render('calendar/month.html.twig', [
            'month' => $month,
            'weeks' => array_values($weeks),
            'project_filter' => $project,
            'projects' => $this->projects->findVisibleFor($user),
        ]);
    }

    #[Route('/week/{year<\d{4}>}/{week<\d{1,2}>}', name: 'calendar_week')]
    public function week(Request $request, int $year, int $week, #[CurrentUser] User $user): Response
    {
        $start = (new \DateTimeImmutable())->setISODate($year, max(1, min(53, $week)))->setTime(0, 0);
        $days = [];
        for ($i = 0; $i < 7; ++$i) {
            $days[] = $start->modify("+$i days");
        }

        return $this->renderTimeline($request, $user, $days, 'week');
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
    public function show(Request $request, CalendarItem $item): Response
    {
        return $this->render('calendar/show.html.twig', [
            'item' => $item,
            'occurrence_date' => self::parseDate($request->query->getString('date')),
        ]);
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
            $this->addFlash('success', $isNew ? 'calendar.created' : 'flash.saved');

            return $this->redirectToRoute('calendar_item_show', ['id' => $item->getId()]);
        }
        if ($form->isSubmitted() && !$isNew) {
            $this->em->refresh($item);
        }

        return $this->render('calendar/form.html.twig', ['form' => $form, 'item' => $isNew ? null : $item]);
    }

    /**
     * @param non-empty-list<\DateTimeImmutable> $days
     */
    private function renderTimeline(Request $request, User $user, array $days, string $mode): Response
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
                    continue;
                }
                // Überlappende Termine nebeneinander versetzen (einfache Spuren)
                $start = $occurrence->startMinute($day);
                $lane = \count(array_filter($timed, static fn (array $t): bool => $t['end'] > $start));
                $timed[] = ['o' => $occurrence, 'start' => $start, 'end' => $occurrence->endMinute($day), 'lane' => min($lane, 3)];
            }
            $columns[] = ['date' => $day, 'all_day' => $allDay, 'timed' => $timed];
        }

        return $this->render('calendar/timeline.html.twig', [
            'mode' => $mode,
            'columns' => $columns,
            'first' => $days[0],
            'project_filter' => $project,
            'projects' => $this->projects->findVisibleFor($user),
        ]);
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
