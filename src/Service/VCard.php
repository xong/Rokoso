<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use App\Entity\User;

/**
 * Minimal vCard reader/writer (RFC 2426, version 3.0; reads 2.1 and 4.0 as far as the fields allow).
 * Own implementation, because the common libraries are not MIT licensed.
 */
final class VCard
{
    /**
     * @param iterable<Contact> $contacts
     */
    public static function export(iterable $contacts): string
    {
        $out = '';
        foreach ($contacts as $contact) {
            $lines = [
                'BEGIN:VCARD',
                'VERSION:3.0',
                'N:'.implode(';', array_map(self::escape(...), [$contact->getLastName() ?? '', $contact->getFirstName() ?? '', '', $contact->getSalutation() ?? '', ''])),
                'FN:'.self::escape($contact->getDisplayName()),
            ];
            $optional = [
                'ORG' => $contact->getCompany(),
                'TITLE' => $contact->getPosition(),
                'EMAIL;TYPE=INTERNET' => $contact->getEmail(),
                'EMAIL;TYPE=INTERNET,HOME' => $contact->getEmail2(),
                'TEL;TYPE=WORK,VOICE' => $contact->getPhone(),
                'TEL;TYPE=CELL' => $contact->getMobile(),
                'URL' => $contact->getWebsite(),
                'BDAY' => $contact->getBirthday()?->format('Y-m-d'),
                'NOTE' => $contact->getNotes(),
            ];
            foreach ($optional as $name => $value) {
                if (null !== $value) {
                    $lines[] = $name.':'.self::escape($value);
                }
            }
            if (null !== $contact->getStreet() || null !== $contact->getCity() || null !== $contact->getPostalCode()) {
                $lines[] = 'ADR;TYPE=WORK:'.implode(';', array_map(self::escape(...), ['', '', $contact->getStreet() ?? '', $contact->getCity() ?? '', '', $contact->getPostalCode() ?? '', '']));
            }
            if ([] !== $contact->getTagList()) {
                $lines[] = 'CATEGORIES:'.implode(',', array_map(self::escape(...), $contact->getTagList()));
            }
            $lines[] = 'END:VCARD';
            foreach ($lines as $line) {
                $out .= self::fold($line)."\r\n";
            }
        }

        return $out;
    }

    /**
     * Reads all cards of a file into new (not persisted) contacts.
     *
     * @return list<Contact>
     */
    public static function parse(string $data, User $creator): array
    {
        // unfold continuation lines (vCard 3/4)
        $data = (string) preg_replace("/\r?\n[ \t]/", '', str_replace("\r\n", "\n", $data));
        $lines = explode("\n", $data);

        $contacts = [];
        $card = null;
        while (null !== $line = array_shift($lines)) {
            if (!preg_match('/^(?:[A-Za-z0-9-]+\.)?([A-Za-z-]+)((?:;[^:]*)?):(.*)$/', $line, $m)) {
                continue;
            }
            $name = strtoupper($m[1]);
            $params = strtoupper($m[2]);
            $value = $m[3];
            if (str_contains($params, 'QUOTED-PRINTABLE')) {
                // vCard 2.1: a trailing "=" continues the value on the next line
                while (str_ends_with($value, '=') && [] !== $lines) {
                    $value = substr($value, 0, -1).array_shift($lines);
                }
                $value = quoted_printable_decode($value);
            }
            if (str_contains($params, 'CHARSET=') && !preg_match('/CHARSET=UTF-?8/', $params) && !mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }

            if ('BEGIN' === $name && 'VCARD' === strtoupper(trim($value))) {
                $card = new Contact($creator);
                continue;
            }
            if (null === $card) {
                continue;
            }
            if ('END' === $name) {
                if ('' !== $card->getDisplayName()) {
                    $contacts[] = $card;
                }
                $card = null;
                continue;
            }
            self::apply($card, $name, $params, $value);
        }

        return $contacts;
    }

    private static function apply(Contact $card, string $name, string $params, string $value): void
    {
        $parts = self::split($value, ';');
        $text = self::unescape($value);
        match ($name) {
            'N' => $card
                ->setLastName(self::cut($parts[0] ?? null, 100))
                ->setFirstName(self::cut(trim(($parts[1] ?? '').' '.($parts[2] ?? '')), 100))
                ->setSalutation(self::cut($parts[3] ?? null, 20)),
            'FN' => '' === $card->getDisplayName() ? self::applyFullName($card, $text) : null,
            'ORG' => $card->setCompany(self::cut($parts[0] ?? null, 150)),
            'TITLE', 'ROLE' => $card->getPosition() ?? $card->setPosition(self::cut($text, 150)),
            'EMAIL' => self::applyEmail($card, $text),
            'TEL' => str_contains($params, 'CELL')
                ? ($card->getMobile() ?? $card->setMobile(self::cut($text, 50)))
                : ($card->getPhone() ?? $card->setPhone(self::cut($text, 50))),
            'ADR' => $card->getStreet() ?? $card
                ->setStreet(self::cut($parts[2] ?? null, 150))
                ->setCity(self::cut($parts[3] ?? null, 100))
                ->setPostalCode(self::cut($parts[5] ?? null, 10)),
            'URL' => $card->setWebsite(false !== filter_var($text, \FILTER_VALIDATE_URL) ? self::cut($text, 255) : null),
            'BDAY' => $card->setBirthday(self::date($text)),
            'NOTE' => $card->setNotes(self::cut($text, 10000)),
            'CATEGORIES' => $card->setTags(self::cut(implode(', ', self::split($value, ',')), 255)),
            default => null,
        };
    }

    private static function applyFullName(Contact $card, string $name): void
    {
        $parts = explode(' ', trim($name));
        $card->setLastName(self::cut(array_pop($parts), 100))->setFirstName(self::cut(implode(' ', $parts), 100));
    }

    private static function applyEmail(Contact $card, string $email): void
    {
        $email = trim($email);
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (null === $card->getEmail()) {
            $card->setEmail($email);
        } elseif (null === $card->getEmail2() && mb_strtolower($email) !== $card->getEmail()) {
            $card->setEmail2($email);
        }
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-?(\d{2})-?(\d{2})/', trim($value), $m)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $m[1].'-'.$m[2].'-'.$m[3]);

        return false === $date ? null : $date;
    }

    private static function cut(?string $value, int $length): ?string
    {
        return null === $value ? null : mb_substr($value, 0, $length);
    }

    /**
     * Splits at unescaped separators and unescapes the parts.
     *
     * @return list<string>
     */
    private static function split(string $value, string $separator): array
    {
        $parts = preg_split('/(?<!\\\\)'.preg_quote($separator, '/').'/', $value) ?: [];

        return array_map(static fn (string $p): string => trim(self::unescape($p)), $parts);
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    private static function unescape(string $value): string
    {
        return strtr($value, ['\\\\' => '\\', '\;' => ';', '\,' => ',', '\n' => "\n", '\N' => "\n"]);
    }

    /** Lines longer than 75 bytes continue on the next line, indented by a space. */
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
