<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\CalendarItem;
use App\Entity\User;
use App\Service\SystemMailer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * .ics files for calendar items: invitations to external guests and the personal subscription feed.
 */
final readonly class CalendarInvitation
{
    public function __construct(
        private SystemMailer $mailer,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private CalendarService $calendar,
    ) {
    }

    /**
     * Sends the item to all guest addresses (not flushed). Returns the number of recipients.
     */
    public function invite(CalendarItem $item, User $sender): int
    {
        $guests = $item->getGuestEmailList();
        if ([] === $guests || $item->isTask() || null === $item->getStartsAt()) {
            return 0;
        }
        $ics = ['body' => $this->itemIcs($item), 'filename' => 'termin.ics', 'mimeType' => 'text/calendar; charset=utf-8; method=PUBLISH'];
        $context = [
            'item' => $item,
            'sender' => $sender->getName(),
            'subject_params' => ['%title%' => $item->getTitle(), '%date%' => $item->getDate()->format('d.m.Y')],
        ];
        foreach ($guests as $guest) {
            $this->mailer->send($guest, 'calendar.invite.subject', 'calendar_invitation', $context, null, [$ics], $sender->getEmail());
        }
        $item->markInvited();

        return \count($guests);
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
        return 'item-'.$item->getId().'@'.(parse_url($this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL), \PHP_URL_HOST) ?: 'coop');
    }
}
