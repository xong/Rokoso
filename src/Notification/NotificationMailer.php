<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\User;
use App\Service\SystemMailer;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Notification emails: one per notification (instant) or a digest.
 */
final readonly class NotificationMailer
{
    public function __construct(
        private SystemMailer $mailer,
        private NotificationText $text,
        private UrlGeneratorInterface $urls,
        private UriSigner $signer,
    ) {
    }

    public function sendInstant(Notification $notification): void
    {
        $recipient = $notification->getRecipient();
        $text = $this->text->text($notification);
        $this->mailer->send($recipient->getEmail(), 'email.notification.subject', 'notification', [
            'subject_params' => ['%text%' => $text],
            'text' => $text,
            'url' => $this->text->link($notification),
            'settings_url' => $this->settingsUrl(),
        ], $recipient->getName(), unsubscribeUrl: $this->unsubscribeUrl($recipient));
    }

    /**
     * Daily email: unread notifications and/or the new mails of the shared mailboxes (only sender, subject and the start of the text).
     *
     * @param list<Notification>                         $notifications
     * @param array{messages: list<Message>, total: int} $mails
     */
    public function sendDigest(User $recipient, array $notifications, array $mails = ['messages' => [], 'total' => 0]): void
    {
        $items = array_map(fn (Notification $n): array => [
            'text' => $this->text->text($n),
            'url' => $this->text->link($n),
            'date' => $n->getCreatedAt(),
        ], $notifications);
        $messages = array_map(fn (Message $m): array => [
            'date' => $m->getDate(),
            'from' => $m->getFromLabel(),
            'from_address' => $m->getFromAddress(),
            'subject' => $m->getSubject(),
            'preview' => self::preview($m->getSnippet()),
            'account' => $m->getMailAccount()?->getName(),
            'url' => $this->urls->generate('mail_show', ['folder' => 'inbox', 'id' => $m->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ], $mails['messages']);
        [$subject, $params] = match (true) {
            [] === $messages => ['email.digest.subject', ['%count%' => \count($items)]],
            [] === $items => ['email.digest.subject_mails', ['%count%' => $mails['total']]],
            default => ['email.digest.subject_both', []],
        };
        $this->mailer->send($recipient->getEmail(), $subject, 'digest', [
            'subject_params' => $params,
            'items' => $items,
            'messages' => $messages,
            'mail_total' => $mails['total'],
            'url' => $this->urls->generate('notification_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'mail_url' => $this->urls->generate('mail_inbox', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'settings_url' => $this->settingsUrl(),
        ], $recipient->getName(), unsubscribeUrl: $this->unsubscribeUrl($recipient));
    }

    /** First 100 characters of the text, cut at a word if possible. */
    public static function preview(string $text, int $length = 100): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return rtrim(false !== $space && $space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, " \t.,;:-").'…';
    }

    /**
     * Signed link that turns notification emails off without logging in (List-Unsubscribe header).
     * Bound to the current address, so it stops working after an email change.
     */
    public function unsubscribeUrl(User $user): string
    {
        return $this->signer->sign($this->urls->generate('notification_unsubscribe', [
            'id' => $user->getId(), 'email' => mb_strtolower($user->getEmail()),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    private function settingsUrl(): string
    {
        return $this->urls->generate('profile_notifications', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
