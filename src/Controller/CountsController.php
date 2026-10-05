<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ConfidentialCaseRepository;
use App\Repository\ForumTopicRepository;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Unread counters for the navigation badges, polled by the live_counts Stimulus controller.
 */
final class CountsController extends AbstractController
{
    #[Route('/counts', name: 'counts', methods: ['GET'])]
    public function counts(
        #[CurrentUser] User $user,
        MessageRepository $messages,
        ForumTopicRepository $topics,
        NotificationRepository $notifications,
        ConfidentialCaseRepository $confidential,
    ): JsonResponse {
        $response = $this->json([
            'inbox' => $messages->countUnreadInbox($user),
            'forum' => $topics->countUnread($user),
            'notifications' => $notifications->countUnread($user),
            'confidential' => $confidential->countUnread($user),
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
