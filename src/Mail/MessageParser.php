<?php

declare(strict_types=1);

namespace App\Mail;

use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\DateHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\IMimePart;

/**
 * Turns a raw RFC 822 message into a ParsedMessage (zbateson/mail-mime-parser).
 */
final class MessageParser
{
    private readonly MailMimeParser $parser;

    public function __construct()
    {
        $this->parser = new MailMimeParser();
    }

    public function parse(string $raw): ParsedMessage
    {
        $message = $this->parser->parse($raw, false);
        $from = $this->addresses($message, 'from')[0] ?? ['name' => '', 'address' => ''];
        $replyTo = $this->addresses($message, 'reply-to')[0]['address'] ?? null;

        $dateHeader = $message->getHeader('date');
        $date = $dateHeader instanceof DateHeader ? $dateHeader->getDateTimeImmutable() : null;

        $attachments = [];
        foreach ($message->getAllAttachmentParts() as $part) {
            $content = $part->getContent() ?? '';
            $stream = $part->getBinaryContentStream();
            if (null !== $stream) {
                $content = $stream->getContents();
            }
            $mimeType = strtolower($part->getContentType('application/octet-stream'));
            $attachments[] = [
                'filename' => $part->getFilename() ?? ('anhang.'.(explode('/', $mimeType)[1] ?? 'bin')),
                'mimeType' => $mimeType,
                'content' => $content,
                'contentId' => $part->getContentId(),
            ];
        }

        return new ParsedMessage(
            messageId: $this->idHeader($message, 'message-id'),
            inReplyTo: $this->idHeader($message, 'in-reply-to'),
            references: $message->getHeaderValue('references'),
            fromAddress: $from['address'],
            fromName: $from['name'],
            replyTo: $replyTo,
            to: $this->addresses($message, 'to'),
            cc: $this->addresses($message, 'cc'),
            subject: trim((string) $message->getHeaderValue('subject', '')),
            date: $date ?? new \DateTimeImmutable(),
            text: $this->body($message->getTextPart()),
            html: $this->body($message->getHtmlPart()),
            attachments: $attachments,
            spamFlagged: $this->spamFlagged($message),
        );
    }

    /**
     * Body of a text part as UTF-8. Many mailers and web forms send UTF-8 without declaring a charset
     * (or claim US-ASCII); the RFC default ISO-8859-1 would turn "ü" into "Ã¼". Such parts are read as
     * UTF-8 when they are valid UTF-8, which real Latin-1 text with umlauts practically never is.
     */
    private function body(?IMessagePart $part): ?string
    {
        if (null === $part) {
            return null;
        }
        $charset = $part instanceof IMimePart ? strtolower((string) $part->getHeaderParameter('content-type', 'charset', '')) : '';
        if (\in_array($charset, ['', 'us-ascii', 'ascii', 'binary'], true)) {
            $raw = $part->getBinaryContentStream()?->getContents();
            if (null !== $raw && mb_check_encoding($raw, 'UTF-8')) {
                return $raw;
            }
        }

        return $part->getContent();
    }

    /**
     * @return list<array{name: string, address: string}>
     */
    private function addresses(IMessage $message, string $header): array
    {
        $h = $message->getHeader($header);
        if (!$h instanceof AddressHeader) {
            return [];
        }
        $result = [];
        foreach ($h->getAddresses() as $address) {
            $email = mb_strtolower(trim($address->getEmail()));
            if ('' !== $email) {
                $result[] = ['name' => trim($address->getName()), 'address' => $email];
            }
        }

        return $result;
    }

    /**
     * SpamAssassin, rspamd and most hosting filters only tag; the mail still reaches the mailbox.
     */
    private function spamFlagged(IMessage $message): bool
    {
        $flag = strtolower(trim((string) $message->getHeaderValue('x-spam-flag', '')));
        $status = strtolower(trim((string) $message->getHeaderValue('x-spam-status', '')));

        return 'yes' === $flag || str_starts_with($status, 'yes');
    }

    private function idHeader(IMessage $message, string $header): ?string
    {
        $value = $message->getHeaderValue($header);

        return null === $value || '' === trim($value) ? null : trim($value, " \t<>");
    }
}
