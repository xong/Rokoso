<?php

declare(strict_types=1);

namespace App\Poll;

use App\Entity\CalendarItem;
use App\Entity\Poll;
use App\Entity\PollAnswer;
use App\Entity\PollBallot;
use App\Entity\PollOption;
use App\Entity\Resolution;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\NotificationType;
use App\Enum\PollKind;
use App\Meeting\MeetingService;
use App\Notification\NotificationCenter;
use App\Repository\ResolutionRepository;
use App\Security\Voter\PollVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creating, voting and closing polls. Only create() flushes (the poll needs an id for the notification link).
 */
final readonly class PollService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ResolutionRepository $resolutions,
        private MeetingService $meetings,
        private NotificationCenter $notifications,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Adds the options: yes/no/abstain for decisions, the given labels for choices, the given slots for date finding.
     *
     * @param list<string>                                                   $labels
     * @param list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable|null}> $slots
     */
    public function create(Poll $poll, array $labels, array $slots, User $actor): void
    {
        if (PollKind::Decision === $poll->getKind()) {
            $labels = array_map(fn (string $key): string => $this->translator->trans($key),
                ['resolution.votes_yes', 'resolution.votes_no', 'resolution.votes_abstain']);
        }
        if (PollKind::Schedule === $poll->getKind()) {
            foreach ($slots as [$start, $end]) {
                $poll->addOption(new PollOption($poll)->setLabel($start->format('d.m.Y H:i'))->setPeriod($start, $end));
            }
        } else {
            foreach ($labels as $label) {
                $poll->addOption(new PollOption($poll)->setLabel($label));
            }
        }
        $this->em->persist($poll);
        $this->em->flush();
        $this->notifications->notify($poll->getEligible(), NotificationType::Poll, $poll->getTitle(),
            $this->urls->generate('poll_show', ['id' => $poll->getId()]), $actor, [PollVoter::VIEW, $poll]);
    }

    /**
     * Stores the answers of a person, replacing earlier ones in open polls.
     *
     * @param array<int, int> $answers option id => PollAnswer::YES|MAYBE (missing = no / not selected)
     *
     * @return bool false if the input is invalid (nothing selected where required, several where only one is allowed)
     */
    public function vote(Poll $poll, User $user, array $answers): bool
    {
        $answers = array_filter($answers, static fn (int $value, int $id): bool => null !== $poll->getOption($id)
            && (PollAnswer::YES === $value || (PollAnswer::MAYBE === $value && PollKind::Schedule === $poll->getKind())), \ARRAY_FILTER_USE_BOTH);
        if (PollKind::Schedule !== $poll->getKind() && (0 === \count($answers) || (!$poll->isMultiple() && \count($answers) > 1))) {
            return false;
        }

        $ballot = $poll->getBallot($user);
        if (null === $ballot) {
            $poll->addBallot($ballot = new PollBallot($poll, $user));
            $this->em->persist($ballot);
        } elseif ($poll->isSecret()) {
            return false;
        } else {
            $ballot->touch();
            foreach ($poll->getOptions() as $option) {
                foreach ($option->getAnswers()->toArray() as $answer) {
                    if ($answer->getBallot() === $ballot) {
                        $option->removeAnswer($answer);
                        $this->em->remove($answer);
                    }
                }
            }
        }
        foreach ($answers as $id => $value) {
            $option = $poll->getOption($id);
            if (null !== $option) {
                $option->addAnswer($answer = new PollAnswer($option, $poll->isSecret() ? null : $ballot, $value));
                $this->em->persist($answer);
            }
        }

        return true;
    }

    /**
     * Ends the poll. Circular resolutions are recorded in the resolution register; for date finding the
     * chosen slot becomes the meeting date or a new calendar event.
     */
    public function close(Poll $poll, User $actor, ?PollOption $chosen = null): void
    {
        $poll->close();
        if ($poll->isCircular() && null === $poll->getResolution()) {
            [$yes, $no, $abstain] = array_map(static fn (PollOption $o): int => $o->getScore(), $poll->getOptions()) + [0, 0, 0];
            $today = new \DateTimeImmutable('today');
            $resolution = new Resolution($poll->getOrganization(), $actor)
                ->setNumber($this->resolutions->nextNumber($poll->getOrganization(), (int) $today->format('Y')))
                ->setTitle($poll->getTitle())
                ->setText($poll->getDescription() ?? $poll->getTitle())
                ->setDecidedOn($today)
                ->setAdopted($yes > $no)
                ->setVotesYes($yes)->setVotesNo($no)->setVotesAbstain($abstain)
                ->setProject($poll->getProject())
                ->setAgendaItem($poll->getAgendaItem());
            $this->em->persist($resolution);
            $poll->setResolution($resolution);
        }
        if (PollKind::Schedule === $poll->getKind() && null !== $chosen && null !== $chosen->getStartsAt()) {
            $meeting = $poll->getMeeting();
            if (null !== $meeting) {
                $duration = null === $meeting->getEndsAt() ? null : $meeting->getEndsAt()->getTimestamp() - $meeting->getStartsAt()->getTimestamp();
                $meeting->setStartsAt($chosen->getStartsAt())
                    ->setEndsAt($chosen->getEndsAt() ?? (null === $duration ? null : $chosen->getStartsAt()->modify('+'.$duration.' seconds')));
                $poll->choose($chosen, $this->meetings->syncCalendar($meeting));
            } else {
                $event = new CalendarItem($actor)->setType(CalendarItemType::Event)->setTitle($poll->getTitle())
                    ->setStartsAt($chosen->getStartsAt())->setEndsAt($chosen->getEndsAt())
                    ->setOrganization($poll->getOrganization())->setProject($poll->getProject())
                    ->setDescription($poll->getDescription());
                foreach ($poll->getBallots() as $ballot) {
                    if (null !== $chosen->getAnswerOf($ballot->getUser())) {
                        $event->addParticipant($ballot->getUser());
                    }
                }
                $this->em->persist($event);
                $poll->choose($chosen, $event);
            }
        }
    }
}
