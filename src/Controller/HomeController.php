<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\CalendarService;
use App\Calendar\Occurrence;
use App\Entity\CalendarItem;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\NotificationType;
use App\Mail\MessageFilter;
use App\Repository\CalendarItemRepository;
use App\Repository\ForumTopicRepository;
use App\Repository\MailAccountRepository;
use App\Repository\MeetingRepository;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use App\Repository\PollRepository;
use App\Service\SetupChecklist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Start page "Heute": what needs the user's attention across all areas.
 */
final class HomeController extends AbstractController
{
    private const int LIMIT = 6;

    #[Route('/', name: 'home')]
    public function home(
        #[CurrentUser] User $user,
        MessageRepository $messages,
        CalendarItemRepository $items,
        CalendarService $calendar,
        MeetingRepository $meetings,
        PollRepository $polls,
        ForumTopicRepository $topics,
        NotificationRepository $notifications,
        SetupChecklist $setup,
        MailAccountRepository $mailAccounts,
    ): Response {
        $now = new \DateTimeImmutable();
        $events = array_filter(
            $calendar->occurrences($user, $now->setTime(0, 0), $now->setTime(0, 0)->modify('+8 days')),
            static fn (Occurrence $o): bool => CalendarItemType::Task !== $o->item->getType() && !$o->isCancelled() && $o->end > $now,
        );
        $recentTopics = $topics->recentFor($user, 20);
        $unreadTopics = $topics->unreadIds($user, $recentTopics);
        $mentions = array_filter(
            $notifications->findForUser($user, true, 50),
            static fn (Notification $n): bool => NotificationType::Mentioned === $n->getType(),
        );

        return $this->render('home/today.html.twig', [
            'messages' => \array_slice($messages->findForList($user, new MessageFilter('inbox', 'mine')), 0, self::LIMIT),
            'tasks' => \array_slice(array_filter(
                $items->findTasks($user, true, withDone: false),
                // due within a week or without a due date
                static fn (CalendarItem $t): bool => null === $t->getStartsAt() || $t->getStartsAt() < $now->modify('+8 days'),
            ), 0, self::LIMIT),
            'events' => \array_slice(array_values($events), 0, self::LIMIT),
            'meetings' => $meetings->findForUser($user, false, null, null, 3),
            'polls' => \array_slice($polls->findAwaitingVote($user), 0, self::LIMIT),
            'topics' => \array_slice(array_values(array_filter($recentTopics, static fn ($t): bool => \in_array($t->getId(), $unreadTopics, true))), 0, self::LIMIT),
            'mentions' => \array_slice(array_values($mentions), 0, self::LIMIT),
            'setup' => $setup->stepsFor($user),
            'sync_problems' => $mailAccounts->findWithSyncProblems($user),
            'now' => $now,
        ]);
    }

    #[Route('/setup/dismiss', name: 'home_setup_dismiss', methods: ['POST'])]
    public function dismissSetup(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('setup-dismiss', $request->getPayload()->getString('_token'))) {
            $user->setSetupDismissed(true);
            $em->flush();
        }

        return $this->redirectToRoute('home');
    }
}
