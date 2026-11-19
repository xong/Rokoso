<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\CalendarItem;
use App\Entity\Organization;
use App\Entity\User;
use App\Mail\GuestDelivery;
use App\Mail\GuestMailer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * .ics files for calendar items: invitations to external guests (from the organization's mailbox) and the personal subscription feed.
 */
final readonly class CalendarInvitation
{
    public function __construct(
        private GuestMailer $guests,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private CalendarService $calendar,
    ) {
    }

    /**
     * Sends the item to all guest addresses from the organization's mailbox and marks it as invited (flushes).
     */
    public function invite(CalendarItem $item, User $sender): GuestDelivery
    {
        $guests = $item->getGuestEmailList();
        if ($item->isTask() || null === $item->getStartsAt()) {
            return new GuestDelivery(0);
        }
        $delivery = $this->guests->send(self::organization($item), $sender, $guests,
            $this->translator->trans('calendar.invite.subject', ['%title%' => $item->getTitle(), '%date%' => $item->getDate()->format('d.m.Y')]),
            $this->twig->render('email/guest_calendar.txt.twig', ['item' => $item, 'sender' => $sender->getName()]),
            $this->attachment($item), $item->getProject());
        if ($delivery->mailed > 0) {
            $item->markInvited();
        }

        return $delivery;
    }

    /** The organization whose mailbox invites the guests */
    public static function organization(CalendarItem $item): ?Organization
    {
        return $item->getOrganization() ?? $item->getProject()?->getOrganization();
    }

    /** @return array{body: string, filename: string, mimeType: string} */
    public function attachment(CalendarItem $item): array
    {
        return ['body' => $this->itemIcs($item), 'filename' => 'termin.ics', 'mimeType' => 'text/calendar; charset=utf-8; method=PUBLISH'];
    }

    /** The item as one event; series with rule, cancelled dates and changed occurrences */
    public function itemIcs(CalendarItem $item): string
    {
        $uid = $this->uid($item);
        $allDay = $item->isAllDay();
        $event = Ics::event($uid, $item->getDate(), $item->getEffectiveEnd(), $allDay, $item->getTitle(), $item->getDescription(), $item->getLocation(), $item->getUrl());
        $events = [];
        $rrule = $item->getRrule();
        if (null !== $rrule) {
            $event[] = 'RRULE:'.$rrule;
            foreach ($item->getExceptions() as $exception) {
                if ($exception->isCancelled()) {
                    $event[] = 'EXDATE'.Ics::moment($exception->getOriginalStart(), $allDay);
                    continue;
                }
                // A changed occurrence as its own event that replaces the original date
                $override = Ics::event($uid, $exception->getStart(), $exception->getEnd(), $allDay, $exception->getTitle() ?? $item->getTitle(),
                    $item->getDescription(), $exception->getLocation() ?? $item->getLocation(), $item->getUrl());
                $override[] = 'RECURRENCE-ID'.Ics::moment($exception->getOriginalStart(), $allDay);
                $events[] = $override;
            }
        }
        array_unshift($events, $event);

        return Ics::document($events, 'Kalender');
    }

    /** Personal subscription: visible occurrences from 60 days back to one year ahead, expanded */
    public function feed(User $user): string
    {
        $now = new \DateTimeImmutable();
        $events = [];
        foreach ($this->calendar->occurrences($user, $now->modify('-60 days'), $now->modify('+1 year')) as $occurrence) {
            $item = $occurrence->item;
            if ($item->isTask() && $item->isDone()) {
                continue;
            }
            $title = $item->isTask() ? $this->translator->trans('calendar.ical.task', ['%title%' => $occurrence->getTitle()]) : $occurrence->getTitle();
            $events[] = Ics::event($this->uid($item).'-'.$occurrence->start->format('Ymd'), $occurrence->start, $occurrence->end, $item->isAllDay(),
                $title, $item->getDescription(), $occurrence->getLocation(),
                $this->urls->generate('calendar_item_show', ['id' => $item->getId(), 'date' => $occurrence->getDay()], UrlGeneratorInterface::ABSOLUTE_URL));
        }

        return Ics::document($events, 'Kalender', 'PUBLISH', $this->translator->trans('calendar.ical.name', ['%name%' => $user->getName()]));
    }

    private function uid(CalendarItem $item): string
    {
        return 'item-'.$item->getId().'@'.(parse_url($this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL), \PHP_URL_HOST) ?: 'rokoso');
    }
}
