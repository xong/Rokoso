<?php

declare(strict_types=1);

namespace App\Calendar;

/**
 * Minimal iCalendar (RFC 5545) writer: events with UTC times or whole days, escaping and line folding.
 */
final class Ics
{
    /**
     * @param list<list<string>> $events lines of each VEVENT (without BEGIN/END)
     */
    public static function document(array $events, string $product, string $method = 'PUBLISH', ?string $name = null): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Rokoso//'.$product.'//DE', 'CALSCALE:GREGORIAN', 'METHOD:'.$method];
        if (null !== $name) {
            array_push($lines, 'X-WR-CALNAME:'.self::escape($name), 'X-WR-TIMEZONE:'.CalendarService::TIMEZONE, 'REFRESH-INTERVAL;VALUE=DURATION:PT1H');
        }
        foreach ($events as $event) {
            array_push($lines, 'BEGIN:VEVENT', ...$event);
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * Lines of one event. Whole-day events use dates (end exclusive), others UTC times.
     *
     * @return list<string>
     */
    public static function event(string $uid, \DateTimeImmutable $start, \DateTimeImmutable $end, bool $allDay, string $summary,
        ?string $description = null, ?string $location = null, ?string $url = null): array
    {
        $lines = ['UID:'.$uid, 'DTSTAMP:'.self::utc(new \DateTimeImmutable())];
        if ($allDay) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$end->setTime(0, 0)->modify('+1 day')->format('Ymd');
        } else {
            $lines[] = 'DTSTART:'.self::utc($start);
            $lines[] = 'DTEND:'.self::utc($end > $start ? $end : $start);
        }
        $lines[] = 'SUMMARY:'.self::escape($summary);
        if (null !== $description && '' !== trim($description)) {
            $lines[] = 'DESCRIPTION:'.self::escape(trim($description));
        }
        if (null !== $location && '' !== $location) {
            $lines[] = 'LOCATION:'.self::escape($location);
        }
        if (null !== $url && '' !== $url) {
            $lines[] = 'URL:'.$url;
        }

        return $lines;
    }

    /** Date or date-time value matching the event start format (for EXDATE / RECURRENCE-ID) */
    public static function moment(\DateTimeImmutable $moment, bool $allDay): string
    {
        return $allDay ? ';VALUE=DATE:'.$moment->format('Ymd') : ':'.self::utc($moment);
    }

    public static function utc(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    public static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /** Folds lines longer than 75 octets without splitting UTF-8 characters. */
    private static function fold(string $line): string
    {
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (\strlen($current) + \strlen($char) > 75) {
                $out .= $current."\r\n ";
                $current = '';
            }
            $current .= $char;
        }

        return $out.$current;
    }
}
