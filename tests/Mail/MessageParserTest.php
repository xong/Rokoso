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
}
