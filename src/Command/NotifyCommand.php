<?php

declare(strict_types=1);

namespace App\Command;

use App\Calendar\CalendarService;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\NotificationEmail;
use App\Enum\NotificationType;
use App\Notification\NotificationCenter;
use App\Notification\NotificationMailer;
use App\Participation\PublicSubmissionHandler;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Due reminders (next 24 hours), leftover instant mails the daily digest and removal of expired public requests. Run via cron, e.g. every 15 minutes.
 */
#[AsCommand(name: 'app:notify', description: 'Erinnerungen und Zusammenfassungen verschicken')]
final readonly class NotifyCommand
{
    private const int DIGEST_HOUR = 7;

    public function __construct(
        private UserRepository $users,
        private NotificationRepository $notifications,
        private NotificationCenter $center,
        private NotificationMailer $mailer,
        private CalendarService $calendar,
        private UrlGeneratorInterface $urls,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private PublicSubmissionHandler $public,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $reminders = 0;
        foreach ($this->users->findBy(['verified' => true]) as $user) {
            $reminders += $this->remindDue($user, $now);
        }
        $this->em->flush();
        $this->center->deliverPending();

        $mails = 0;
        foreach ($this->notifications->findRecipientsWithUnmailed() as $user) {
            $mails += $this->mail($user, $now);
        }
        $this->em->flush();

        $purged = $this->public->purgeExpired();

        $io->writeln(\sprintf('%d Erinnerung(en), %d E-Mail(s), %d abgelaufene Anfrage(n) entfernt', $reminders, $mails, $purged));

        return 0;
    }

    private function remindDue(User $user, \DateTimeImmutable $now): int
    {
        $created = 0;
        foreach ($this->calendar->occurrences($user, $now, $now->modify('+24 hours')) as $occurrence) {
            $item = $occurrence->item;
            if ($occurrence->start < $now) {
                continue;
            }
            $concerned = CalendarItemType::Task === $item->getType()
                ? !$item->isDone() && $item->getAssignees()->contains($user)
                : $item->getParticipants()->contains($user);
            if (!$concerned) {
                continue;
            }
            $created += \count($this->center->notify([$user], NotificationType::Due, $item->getTitle(),
                $this->urls->generate('calendar_item_show', ['id' => $item->getId()]),
                refKey: \sprintf('due:%d:%s', $item->getId(), $occurrence->start->format('YmdHi'))));
        }

        return $created;
    }

    /**
     * Instant mode: mails that were not sent right away. Daily mode: one digest per day from 7 o'clock.
     */
    private function mail(User $user, \DateTimeImmutable $now): int
    {
        try {
            switch ($user->getNotificationEmail()) {
                case NotificationEmail::Instant:
                    $pending = $this->notifications->findUnmailed($user, $now->modify('-5 minutes'));
                    foreach ($pending as $notification) {
                        $this->mailer->sendInstant($notification);
                        $notification->markEmailed();
                    }

                    return \count($pending);
                case NotificationEmail::Daily:
                    $today = $now->setTime(0, 0);
                    if ((int) $now->format('G') < self::DIGEST_HOUR || ($user->getLastDigestAt() ?? new \DateTimeImmutable('@0')) >= $today) {
                        return 0;
                    }
                    $pending = $this->notifications->findUnmailed($user);
                    if ([] === $pending) {
                        return 0;
                    }
                    $this->mailer->sendDigest($user, $pending);
                    foreach ($pending as $notification) {
                        $notification->markEmailed();
                    }
                    $user->setLastDigestAt($now);

                    return 1;
                case NotificationEmail::Off:
                    return 0;
            }
        } catch (\Throwable) {
            return 0;
        }
    }
}
