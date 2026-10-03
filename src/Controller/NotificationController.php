<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ForumBoard;
use App\Entity\ForumTopic;
use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Enum\NotificationEmail;
use App\Notification\PushSender;
use App\Repository\NotificationRepository;
use App\Repository\PushSubscriptionRepository;
use App\Repository\WatchRepository;
use App\Security\Voter\ForumVoter;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Bell (notification list), watching, notification settings and push subscriptions.
 */
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notifications,
    ) {
    }

    #[Route('/notifications', name: 'notification_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $unreadOnly = $request->query->getBoolean('unread');

        return $this->render('notification/index.html.twig', [
            'notifications' => $this->notifications->findForUser($user, $unreadOnly),
            'unread_only' => $unreadOnly,
            'unread_count' => $this->notifications->countUnread($user),
        ]);
    }

    /** Marks as read and opens the target. */
    #[Route('/notifications/{id<\d+>}', name: 'notification_open')]
    public function open(Notification $notification, #[CurrentUser] User $user): Response
    {
        if ($notification->getRecipient() !== $user) {
            throw $this->createNotFoundException();
        }
        $notification->markRead();
        $this->em->flush();
        $url = $notification->getUrl();

        return $this->redirect(str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : $this->generateUrl('notification_index'));
    }

    #[Route('/notifications/read-all', name: 'notification_read_all', methods: ['POST'])]
    #[IsCsrfTokenValid('notifications')]
    public function readAll(#[CurrentUser] User $user): Response
    {
        $this->notifications->markAllRead($user);
        $this->addFlash('success', 'notification.all_read');

        return $this->redirectToRoute('notification_index');
    }

    /**
     * Toggles watching a forum board, topic or project.
     */
    #[Route('/watch/{type<board|topic|project>}/{id<\d+>}', name: 'watch_toggle', methods: ['POST'])]
    #[IsCsrfTokenValid('watch')]
    public function toggleWatch(Request $request, string $type, int $id, WatchRepository $watches, #[CurrentUser] User $user): Response
    {
        [$class, $attribute] = match ($type) {
            'board' => [ForumBoard::class, ForumVoter::VIEW],
            'topic' => [ForumTopic::class, ForumVoter::VIEW],
            default => [Project::class, ProjectVoter::VIEW],
        };
        $target = $this->em->find($class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted($attribute, $target);

        $watch = $watches->findWatch($user, $target);
        if (null !== $watch) {
            $this->em->remove($watch);
            $this->addFlash('success', 'watch.removed');
        } else {
            $watches->watch($user, $target);
            $this->addFlash('success', 'watch.added');
        }
        $this->em->flush();

        $back = $request->getPayload()->getString('redirect');

        return $this->redirect(str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : $this->generateUrl('notification_index'));
    }

    #[Route('/profile/notifications', name: 'profile_notifications')]
    public function settings(Request $request, PushSender $push, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('notification-settings', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $mode = NotificationEmail::tryFrom($request->getPayload()->getString('email'));
            if (null !== $mode) {
                $user->setNotificationEmail($mode);
                $this->em->flush();
                $this->addFlash('success', 'flash.saved');
            }

            return $this->redirectToRoute('profile_notifications');
        }

        return $this->render('profile/notifications.html.twig', [
            'section' => 'notifications',
            'modes' => NotificationEmail::cases(),
            'push_key' => $push->isEnabled() ? $push->getPublicKey() : null,
        ]);
    }

    /**
     * Stores or removes the browser's push subscription (JSON from the push Stimulus controller).
     */
    #[Route('/profile/push', name: 'profile_push', methods: ['POST', 'DELETE'])]
    public function push(Request $request, PushSubscriptionRepository $subscriptions, #[CurrentUser] User $user): JsonResponse
    {
        if (!$this->isCsrfTokenValid('push', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }
        /** @var array{endpoint?: mixed, keys?: array{p256dh?: mixed, auth?: mixed}} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $endpoint = \is_string($data['endpoint'] ?? null) ? $data['endpoint'] : '';
        if (!str_starts_with($endpoint, 'https://') || \strlen($endpoint) > 1000) {
            return new JsonResponse(['error' => 'endpoint'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $subscriptions->findByEndpoint($endpoint);
        if (null !== $existing) {
            $this->em->remove($existing);
            $this->em->flush();
        }
        if ($request->isMethod('POST')) {
            $p256dh = $data['keys']['p256dh'] ?? null;
            $auth = $data['keys']['auth'] ?? null;
            if (!\is_string($p256dh) || !\is_string($auth) || \strlen($p256dh) > 255 || \strlen($auth) > 255) {
                return new JsonResponse(['error' => 'keys'], Response::HTTP_BAD_REQUEST);
            }
            $this->em->persist(new PushSubscription($user, $endpoint, $p256dh, $auth));
            $this->em->flush();
        }

        return new JsonResponse(['ok' => true]);
    }
}
