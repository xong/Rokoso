<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends Koopio's own system mails (confirmation, password reset, invitations, meeting invitations).
 */
final readonly class SystemMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Autowire(env: 'MAILER_FROM')]
        private string $from,
    ) {
    }

    /**
     * @param array<string, mixed>                                          $context
     * @param list<array{body: string, filename: string, mimeType: string}> $attachments
     */
    public function send(string $to, string $subjectKey, string $template, array $context = [], ?string $toName = null, array $attachments = [], ?string $replyTo = null): void
    {
        $subject = $this->translator->trans($subjectKey, $context['subject_params'] ?? []);
        $email = (new TemplatedEmail())
            ->from(Address::create($this->from))
            ->to(new Address($to, $toName ?? ''))
            ->subject($subject)
            ->htmlTemplate('email/'.$template.'.html.twig')
            ->textTemplate('email/'.$template.'.txt.twig')
            ->context($context + ['subject' => $subject]);
        foreach ($attachments as $attachment) {
            $email->attach($attachment['body'], $attachment['filename'], $attachment['mimeType']);
        }
        if (null !== $replyTo) {
            $email->replyTo($replyTo);
        }

        $this->mailer->send($email);
    }
}
