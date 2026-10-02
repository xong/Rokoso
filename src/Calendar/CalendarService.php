<?php

declare(strict_types=1);

namespace App\Calendar;

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
        $result = [];
        foreach ($this->items->findCandidates($user, $from, $to, $projectId) as $item) {
            $duration = $item->getDuration();
            $rrule = $item->getRrule();
            if (null === $rrule) {
                $occurrence = new Occurrence($item, $item->getStartsAt(), $item->getEffectiveEnd());
                if ($occurrence->start < $to && $occurrence->end >= $from) {
                    $result[] = $occurrence;
                }
                continue;
            }

            $rule = new RRule($rrule, $item->getStartsAt());
            // Auch Termine einbeziehen, die vor dem Zeitraum beginnen und hineinragen
            foreach ($rule->getOccurrencesBetween($from->sub($duration), $to) as $start) {
                $start = \DateTimeImmutable::createFromInterface($start);
                $end = $start->add($duration);
                if ($start < $to && $end >= $from) {
                    $result[] = new Occurrence($item, $start, $end);
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
