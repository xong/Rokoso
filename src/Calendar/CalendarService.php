<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\CalendarItem;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\CalendarItemRepository;
use RRule\RRule;

/**
 * Expands visible calendar items (including recurring ones) into occurrences for a date range.
 */
final readonly class CalendarService
{
    public const string TIMEZONE = 'Europe/Berlin';

    public function __construct(private CalendarItemRepository $items)
    {
    }

    /**
     * @return list<Occurrence> sorted by start
     */
    public function occurrences(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to, ?int $projectId = null): array
    {
        return $this->expand($this->items->findCandidates($user, $from, $to, $projectId), $from, $to);
    }

    /**
     * Public events of an organization (for its public page).
     *
     * @return list<Occurrence> sorted by start
     */
    public function publicOccurrences(Organization $organization, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->expand($this->items->findPublicCandidates($organization, $from, $to), $from, $to);
    }

    /**
     * The occurrence of a recurring item on its original day (also when cancelled); null if the series has none that day.
     */
    public function occurrenceOn(CalendarItem $item, \DateTimeImmutable $day): ?Occurrence
    {
        if (!$item->isRecurring() || !$item->occursOn($day)) {
            return null;
        }
        $exception = $item->getException($day);
        if (null !== $exception) {
            return new Occurrence($item, $exception->getStart(), $exception->getEnd(), $exception);
        }
        $time = $item->getDate();
        $start = $day->setTime((int) $time->format('G'), (int) $time->format('i'));

        return new Occurrence($item, $start, $start->add($item->getDuration()));
    }

    /**
     * @param list<CalendarItem> $items
     *
     * @return list<Occurrence>
     */
    private function expand(array $items, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = [];
        foreach ($items as $item) {
            $duration = $item->getDuration();
            $rrule = $item->getRrule();
            if (null === $rrule) {
                $occurrence = new Occurrence($item, $item->getDate(), $item->getEffectiveEnd());
                if ($occurrence->start < $to && $occurrence->end >= $from) {
                    $result[] = $occurrence;
                }
                continue;
            }

            $rule = new RRule($rrule, $item->getDate());
            // Also include occurrences that start before the range and reach into it
            foreach ($rule->getOccurrencesBetween($from->sub($duration), $to) as $start) {
                $start = \DateTimeImmutable::createFromInterface($start);
                if (null !== $item->getException($start)) {
                    continue;
                }
                $end = $start->add($duration);
                if ($start < $to && $end >= $from) {
                    $result[] = new Occurrence($item, $start, $end);
                }
            }
            // Changed occurrences wherever they were moved to
            foreach ($item->getExceptions() as $exception) {
                if (!$exception->isCancelled() && $exception->getStart() < $to && $exception->getEnd() >= $from) {
                    $result[] = new Occurrence($item, $exception->getStart(), $exception->getEnd(), $exception);
                }
            }
        }
        usort($result, static fn (Occurrence $a, Occurrence $b): int => [$b->isAllDayLike(), $a->start] <=> [$a->isAllDayLike(), $b->start]);

        return $result;
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return list<Occurrence>
     */
    public static function forDay(array $occurrences, \DateTimeImmutable $day): array
    {
        return array_values(array_filter($occurrences, static fn (Occurrence $o): bool => $o->coversDay($day)));
    }
}
