<?php

declare(strict_types=1);

namespace App\Meeting;

use App\Calendar\Ics;
use App\Entity\CalendarItem;
use App\Entity\Meeting;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Repository\CalendarItemRepository;
use App\Service\SystemMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Keeps the calendar entry of a meeting in sync and sends invitations with an .ics file.
 */
final readonly class MeetingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CalendarItemRepository $calendarItems,
        private SystemMailer $mailer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** Creates or updates the calendar event of the meeting (persisted, not flushed). */
    public function syncCalendar(Meeting $meeting): CalendarItem
    {
        $item = null === $meeting->getId() ? null : $this->calendarItems->findOneBy(['meeting' => $meeting]);
        if (null === $item) {
            $item = new CalendarItem($meeting->getCreatedBy())->setType(CalendarItemType::Event)->setMeeting($meeting);
            $this->em->persist($item);
        }
        $item->setTitle($meeting->getTitle())
            ->setStartsAt($meeting->getStartsAt())
            ->setEndsAt($meeting->getEffectiveEnd())
            ->setLocation($meeting->getLocation())
            ->setUrl($meeting->getVideoUrl())
            ->setOrganization($meeting->getOrganization())
            ->setProject($meeting->getProject());

        return $item;
    }

    /**
     * Sends the invitation to all members and guests and marks the meeting as invited (not flushed).
     *
     * @return int number of recipients
     */
    public function invite(Meeting $meeting, User $sender): int
    {
        $url = $this->urls->generate('meeting_show', ['id' => $meeting->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $ics = ['body' => $this->ics($meeting, $url), 'filename' => 'sitzung.ics', 'mimeType' => 'text/calendar; charset=utf-8; method=PUBLISH'];
        $context = [
            'meeting' => $meeting,
            'sender' => $sender->getName(),
            'subject_params' => ['%title%' => $meeting->getTitle(), '%date%' => $meeting->getStartsAt()->format('d.m.Y')],
        ];

        $count = 0;
        $sent = [];
        foreach ($meeting->getOrganization()->getMembers() as $member) {
            $this->mailer->send($member->getEmail(), 'meeting.invitation.subject', 'meeting_invitation', $context + ['url' => $url], $member->getName(), [$ics], $sender->getEmail());
            $sent[mb_strtolower($member->getEmail())] = true;
            ++$count;
        }
        foreach ($meeting->getGuestEmailList() as $guest) {
            if (isset($sent[$guest])) {
                continue;
            }
            $this->mailer->send($guest, 'meeting.invitation.subject', 'meeting_invitation', $context + ['url' => null], null, [$ics], $sender->getEmail());
            ++$count;
        }
        $meeting->markInvited();

        return $count;
    }

    /** iCalendar (RFC 5545) file of the meeting. */
    public function ics(Meeting $meeting, string $url): string
    {
        $agenda = [];
        foreach ($meeting->getAgenda() as $item) {
            $agenda[] = 'TOP '.$item->getNumber().': '.$item->getTitle();
        }
        $description = trim(implode("\n", array_filter([$meeting->getDescription(), implode("\n", $agenda), $meeting->getVideoUrl(), $url])));

        return Ics::document([Ics::event('meeting-'.$meeting->getId().'@'.(parse_url($url, \PHP_URL_HOST) ?: 'rokoso'),
            $meeting->getStartsAt(), $meeting->getEffectiveEnd(), false, $meeting->getTitle(), $description,
            $meeting->getLocation() ?? $meeting->getVideoUrl(), $url)], 'Sitzungen');
    }
}
