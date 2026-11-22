<?php

declare(strict_types=1);

namespace App\Command;

use App\Calendar\CalendarService;
use App\Entity\CalendarItem;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\NotificationEmail;
use App\Enum\NotificationType;
use App\Notification\NotificationCenter;
use App\Notification\NotificationMailer;
use App\Participation\PublicSubmissionHandler;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\FileStorage;
use App\Service\RetentionCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Reminders (per item: 15 minutes to one week before the start), leftover instant mails the daily digest, removal of expired public requests and of unsaved shared files, deletion periods. Run via cron, e.g. every 15 minutes.
 */
#[AsCommand(name: 'app:notify', description: 'Erinnerungen und Zusammenfassungen verschicken')]
final readonly class NotifyCommand
{
    private const int DIGEST_HOUR = 7;

    public function __construct(
        private UserRepository $users,
        private NotificationRepository $notifications,
        private MessageRepository $messages,
        private NotificationCenter $center,
        private NotificationMailer $mailer,
        private CalendarService $calendar,
        private UrlGeneratorInterface $urls,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private PublicSubmissionHandler $public,
        private FileStorage $files,
        private RetentionCleaner $retention,
        private LoggerInterface $logger,
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
        $recipients = [];
        foreach ($this->notifications->findRecipientsWithUnmailed() as $user) {
            $recipients[(int) $user->getId()] = $user;
        }
        // the summary of new mails also goes out without any notification
        foreach ($this->users->findBy(['mailDigest' => true, 'verified' => true]) as $user) {
            $recipients[(int) $user->getId()] = $user;
        }
        foreach ($recipients as $user) {
            if ($user->isActive()) {
                $mails += $this->mail($user, $now);
            }
        }
        $this->em->flush();

        $purged = $this->public->purgeExpired();
        // files shared to the app (PWA) that were not saved within a day
        $incoming = $this->files->purgeIncoming($now->modify('-1 day'));

        // deletion periods (trash, old messages, public submissions, security log)
        $cleaned = $this->retention->clean();

        $io->writeln(\sprintf('%d Erinnerung(en), %d E-Mail(s), %d abgelaufene Anfrage(n), %d geteilte Datei(en) entfernt', $reminders, $mails, $purged, $incoming));
        $io->writeln(\sprintf('Löschfristen: %d Nachricht(en), %d Einsendung(en), %d vertrauliche(s) Gespräch(e), %d Protokolleinträge, %d Agenten-Zugänge', $cleaned['messages'], $cleaned['submissions'], $cleaned['confidential'], $cleaned['security'], $cleaned['agents']));

        return 0;
    }

    private function remindDue(User $user, \DateTimeImmutable $now): int
    {
        $created = 0;
        $longest = max(CalendarItem::REMINDER_CHOICES);
        foreach ($this->calendar->occurrences($user, $now, $now->modify(\sprintf('+%d minutes', $longest))) as $occurrence) {
            $item = $occurrence->item;
            $minutes = $item->getReminderMinutes();
            if (null === $minutes || $occurrence->start < $now || $occurrence->start->modify(\sprintf('-%d minutes', $minutes)) > $now) {
                continue;
            }
            // Assignees resp. participants; without any, the creator
            $people = CalendarItemType::Task === $item->getType() ? $item->getAssignees() : $item->getParticipants();
            $concerned = $people->isEmpty() ? $item->getCreatedBy()->getId() === $user->getId() : $people->contains($user);
            if (!$concerned || $item->isDone()) {
                continue;
            }
            $created += \count($this->center->notify([$user], NotificationType::Due, $occurrence->getTitle(),
                $this->urls->generate('calendar_item_show', ['id' => $item->getId()] + ($item->isRecurring() ? ['date' => $occurrence->getDay()] : [])),
                refKey: \sprintf('due:%d:%s', $item->getId(), $occurrence->start->format('YmdHi'))));
        }

        return $created;
    }

    /**
     * Instant mode: mails that were not sent right away. Daily mode: one digest per day from 7 o'clock,
     * together with the summary of new mails if chosen (which otherwise goes out on its own at the same time).
     */
    private function mail(User $user, \DateTimeImmutable $now): int
    {
        try {
            $sent = 0;
            $pending = [];
            if (NotificationEmail::Instant === $user->getNotificationEmail()) {
                foreach ($this->notifications->findUnmailed($user, $now->modify('-5 minutes')) as $notification) {
                    $this->mailer->sendInstant($notification);
                    $notification->markEmailed();
                    ++$sent;
                }
            } elseif (NotificationEmail::Daily === $user->getNotificationEmail() && $this->digestDue($user->getLastDigestAt(), $now)) {
                $pending = $this->notifications->findUnmailed($user);
            }

            $mails = null;
            if ($user->wantsMailDigest() && $this->digestDue($user->getLastMailDigestAt(), $now)) {
                $mails = $this->messages->findForDigest($user, $user->getLastMailDigestAt() ?? $now->modify('-1 day'));
            }
            if ([] !== $pending || 0 < ($mails['total'] ?? 0)) {
                $this->mailer->sendDigest($user, $pending, $mails ?? ['messages' => [], 'total' => 0]);
                ++$sent;
            }
            foreach ($pending as $notification) {
                $notification->markEmailed();
            }
            if ([] !== $pending) {
                $user->setLastDigestAt($now);
            }
            // also without new mails, so the next summary starts from here
            if (null !== $mails) {
                $user->setLastMailDigestAt($now);
            }

            return $sent;
        } catch (\Throwable $e) {
            $this->logger->error('Notification mail to user {id} failed: {message}', ['id' => $user->getId(), 'message' => $e->getMessage()]);

            return 0;
        }
    }

    /** From 7 o'clock, once a day. */
    private function digestDue(?\DateTimeImmutable $last, \DateTimeImmutable $now): bool
    {
        return (int) $now->format('G') >= self::DIGEST_HOUR && ($last ?? new \DateTimeImmutable('@0')) < $now->setTime(0, 0);
    }
}
