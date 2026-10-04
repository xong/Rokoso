<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Contact;
use App\Entity\User;
use App\Service\VCard;
use PHPUnit\Framework\TestCase;

final class VCardTest extends TestCase
{
    public function testExportAndParseRoundTrip(): void
    {
        $user = (new User())->setEmail('anna@example.org')->setName('Anna');
        $contact = (new Contact($user))->setFirstName('Jörg')->setLastName('Müller')->setCompany('GS Nord; Haus B')
            ->setPosition('Schulleiter')->setEmail('mueller@example.org')->setPhone('0123 456')
            ->setStreet('Hauptstraße 1')->setPostalCode('12345')->setCity('Musterstadt')
            ->setNotes(str_repeat('Lange Notiz, ', 10));

        $card = VCard::export([$contact]);
        self::assertStringContainsString("BEGIN:VCARD\r\nVERSION:3.0\r\n", $card);
        self::assertStringContainsString('ORG:GS Nord\; Haus B', $card);
        foreach (explode("\r\n", $card) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line));
        }

        [$parsed] = VCard::parse($card, $user);
        self::assertSame('Jörg', $parsed->getFirstName());
        self::assertSame('Müller', $parsed->getLastName());
        self::assertSame('GS Nord; Haus B', $parsed->getCompany());
        self::assertSame('Schulleiter', $parsed->getPosition());
        self::assertSame('mueller@example.org', $parsed->getEmail());
        self::assertSame('Musterstadt', $parsed->getCity());
        self::assertSame(trim(str_repeat('Lange Notiz, ', 10)), trim((string) $parsed->getNotes()));
    }

    public function testParsesOutlookStyleCards(): void
    {
        $data = "BEGIN:VCARD\nVERSION:2.1\nN;CHARSET=UTF-8:Becker;Jonas\nEMAIL;PREF;INTERNET:JONAS@example.org\nEMAIL;INTERNET:jb@example.org\n"
            ."NOTE;ENCODING=QUOTED-PRINTABLE:Erste Zeile=0D=0A=\nzweite Zeile\nEND:VCARD\n"
            ."BEGIN:VCARD\nVERSION:3.0\nEMAIL:ohne-name@example.org\nEND:VCARD\n";

        $contacts = VCard::parse($data, new User());
        self::assertCount(1, $contacts);
        self::assertSame('Jonas Becker', $contacts[0]->getDisplayName());
        self::assertSame('jonas@example.org', $contacts[0]->getEmail());
        self::assertSame('jb@example.org', $contacts[0]->getEmail2());
        self::assertStringContainsString('zweite Zeile', (string) $contacts[0]->getNotes());
    }
}
