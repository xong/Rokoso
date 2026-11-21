<?php

declare(strict_types=1);

namespace App\Meeting;

use App\Calendar\Ics;
use App\Entity\CalendarItem;
use App\Entity\Meeting;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Mail\MailboxDelivery;
use App\Mail\OrganizationMailer;
use App\Notification\ActivityNotifier;
use App\Repository\CalendarItemRepository;
use App\Service\SystemMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Keeps the calendar entry of a meeting in sync and sends invitations with an .ics file.
 */
final readonly class MeetingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CalendarItemRepository $calendarItems,
        private SystemMailer $mailer,
        private OrganizationMailer $organizationMailer,
        private Environment $twig,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
        private ActivityNotifier $notifier,
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
     * Sends the invitation, notifies the members and marks the meeting as invited (flushes when mailed via the mailbox).
     * Everything goes out from the organization's mailbox (members with the link to the meeting, guests without);
     * without a mailbox members get it as system mail and guests nothing.
     *
     * @return array{members: MailboxDelivery, guests: MailboxDelivery}
     */
    public function invite(Meeting $meeting, User $sender): array
    {
        $organization = $meeting->getOrganization();
        $url = $this->urls->generate('meeting_show', ['id' => $meeting->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $ics = ['body' => $this->ics($meeting, $url), 'filename' => 'sitzung.ics', 'mimeType' => 'text/calendar; charset=utf-8; method=PUBLISH'];
        $subjectParams = ['%title%' => $meeting->getTitle(), '%date%' => $meeting->getStartsAt()->format('d.m.Y')];
        $subject = $this->translator->trans('meeting.invitation.subject', $subjectParams);
        $context = ['meeting' => $meeting, 'sender' => $sender->getName()];

        $members = [];
        foreach ($organization->getMembers() as $member) {
            $members[mb_strtolower($member->getEmail())] = $member;
        }
        $guests = array_values(array_filter($meeting->getGuestEmailList(), static fn (string $guest): bool => !isset($members[$guest])));
        $meeting->markInvited();
        $this->notifier->meetingInvited($meeting, $sender);

        if (null === $this->organizationMailer->account($organization, $sender)) {
            foreach ($members as $member) {
                $this->mailer->send($member->getEmail(), 'meeting.invitation.subject', 'meeting_invitation', $context + ['url' => $url, 'subject_params' => $subjectParams], $member->getName(), [$ics], $sender->getEmail());
            }
            $memberDelivery = new MailboxDelivery(\count($members));
        } else {
            $memberDelivery = $this->organizationMailer->send($organization, $sender, array_map(static fn (User $member): string => $member->getEmail(), array_values($members)),
                $subject, $this->twig->render('email/mailbox_meeting.txt.twig', $context + ['url' => $url]), $ics, $meeting->getProject());
        }
        $guestDelivery = $this->organizationMailer->send($organization, $sender, $guests,
            $subject, $this->twig->render('email/mailbox_meeting.txt.twig', $context), $ics, $meeting->getProject());

        return ['members' => $memberDelivery, 'guests' => $guestDelivery];
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
