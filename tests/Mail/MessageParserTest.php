<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Mail\MessageParser;
use PHPUnit\Framework\TestCase;

final class MessageParserTest extends TestCase
{
    public function testParsesHeadersBodiesAndAttachments(): void
    {
        $parsed = new MessageParser()->parse((string) file_get_contents(__DIR__.'/../fixtures/simple.eml'));

        self::assertSame('abc123@example.org', $parsed->messageId);
        self::assertSame('eva@example.org', $parsed->fromAddress);
        self::assertSame('Eva Müller', $parsed->fromName);
        self::assertSame('Frage zur Schulwegsicherheit', $parsed->subject);
        self::assertSame([['name' => 'Stadtelternvertretung', 'address' => 'sev@example.org'], ['name' => '', 'address' => 'info@example.org']], $parsed->to);
        self::assertSame('bert@example.org', $parsed->cc[0]['address']);
        self::assertSame('2026-10-01T07:15:00+00:00', $parsed->date->setTimezone(new \DateTimeZone('UTC'))->format('c'));
        self::assertStringContainsString('Viele Grüße', (string) $parsed->text);
        self::assertStringContainsString('<b>Zebrastreifen</b>', (string) $parsed->html);
        self::assertCount(1, $parsed->attachments);
        self::assertSame('plan.pdf', $parsed->attachments[0]['filename']);
        self::assertSame('application/pdf', $parsed->attachments[0]['mimeType']);
        self::assertStringStartsWith('%PDF', $parsed->attachments[0]['content']);
    }

    public function testBodiesWithoutCharsetAreReadAsUtf8WhenValid(): void
    {
        $parser = new MessageParser();
        $mail = static fn (string $contentType, string $body): string => "From: a@example.org\r\nSubject: Test\r\nMIME-Version: 1.0\r\n"
            ."Content-Type: {$contentType}\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$body}\r\n";

        self::assertSame('Schöne Grüße', trim((string) $parser->parse($mail('text/plain', 'Schöne Grüße'))->text));
        self::assertSame('<p>Schöne Grüße</p>', trim((string) $parser->parse($mail('text/html', '<p>Schöne Grüße</p>'))->html));
        self::assertSame('Schöne Grüße', trim((string) $parser->parse($mail('text/plain; charset=us-ascii', 'Schöne Grüße'))->text));
        // real Latin-1 without a charset keeps the RFC default
        self::assertSame('Schöne Grüße', trim((string) $parser->parse($mail('text/plain', (string) mb_convert_encoding('Schöne Grüße', 'ISO-8859-1', 'UTF-8')))->text));
        self::assertSame('Grüße „Test“', trim((string) $parser->parse($mail('text/plain; charset=windows-1252', (string) mb_convert_encoding('Grüße „Test“', 'Windows-1252', 'UTF-8')))->text));
    }
}
