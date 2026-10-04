<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\CalendarInvitation;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Personal calendar subscription (iCal) via a secret link, and its management in the profile.
 */
final class CalendarFeedController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** Public, only protected by the secret token in the URL */
    #[Route('/ical/{token<[a-f0-9]{64}>}.ics', name: 'calendar_feed', methods: ['GET'])]
    public function feed(string $token, UserRepository $users, CalendarInvitation $invitation): Response
    {
        $user = $users->findOneBy(['calendarToken' => $token]) ?? throw $this->createNotFoundException();

        return new Response($invitation->feed($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="coop.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    #[Route('/profile/calendar', name: 'profile_calendar', methods: ['GET'])]
    public function settings(#[CurrentUser] User $user): Response
    {
        $token = $user->getCalendarToken();
        $url = null === $token ? null : $this->generateUrl('calendar_feed', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('profile/calendar.html.twig', [
            'section' => 'calendar',
            'feed_url' => $url,
            'webcal_url' => null === $url ? null : preg_replace('#^https?://#', 'webcal://', $url),
        ]);
    }

    /** Creates a new link; an old one stops working */
    #[Route('/profile/calendar/reset', name: 'profile_calendar_reset', methods: ['POST'])]
    #[IsCsrfTokenValid('profile-calendar')]
    public function reset(#[CurrentUser] User $user): Response
    {
        $user->resetCalendarToken();
        $this->em->flush();
        $this->addFlash('success', 'profile.calendar.created');

        return $this->redirectToRoute('profile_calendar');
    }

    #[Route('/profile/calendar/disable', name: 'profile_calendar_disable', methods: ['POST'])]
    #[IsCsrfTokenValid('profile-calendar')]
    public function disable(#[CurrentUser] User $user): Response
    {
        $user->resetCalendarToken(false);
        $this->em->flush();
        $this->addFlash('success', 'profile.calendar.disabled');

        return $this->redirectToRoute('profile_calendar');
    }
}
