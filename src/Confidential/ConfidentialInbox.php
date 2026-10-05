<?php

declare(strict_types=1);

namespace App\Confidential;

use App\Entity\ConfidentialCase;
use App\Entity\PublicSettings;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Notification\NotificationCenter;
use App\Repository\ConfidentialCaseRepository;
use App\Security\Voter\ConfidentialCaseVoter;
use App\Service\SystemMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Anonymous confidential contact: opens conversations, adds messages from both sides and decrypts them for display.
 * Notices (to confidants and to the optional email address) never contain the content.
 */
final readonly class ConfidentialInbox
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConfidentialCaseRepository $cases,
        private ConfidentialCrypto $crypto,
        private NotificationCenter $notifications,
        private SystemMailer $mailer,
        private UrlGeneratorInterface $urls,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Opens a conversation; returns the access code, which is shown only once.
     *
     * @return array{ConfidentialCase, string}
     */
    public function open(PublicSettings $settings, string $subject, string $body, ?string $email): array
    {
        do {
            $code = $this->crypto->newCode();
            $hash = $this->crypto->lookupHash($code);
        } while (null !== $this->cases->findOneBy(['lookupHash' => $hash]));

        $key = $this->crypto->newKey();
        $case = new ConfidentialCase($settings->getOrganization(), $hash, $this->crypto->wrapKey($key), $this->crypto->encrypt(trim($subject), $key));
        $email = trim((string) $email);
        if ('' !== $email) {
            $case->setEmail($this->crypto->encrypt($email, $key));
        }
        $case->addMessage($this->crypto->encrypt(trim($body), $key), null);
        $this->em->persist($case);
        $this->em->flush();
        $this->notifyConfidants($case);
        $this->em->flush();

        return [$case, $code];
    }

    public function find(PublicSettings $settings, string $code): ?ConfidentialCase
    {
        if (!ConfidentialCrypto::isWellFormed($code)) {
            return null;
        }

        return $this->cases->findOneBy(['lookupHash' => $this->crypto->lookupHash($code), 'organization' => $settings->getOrganization()]);
    }

    public function replyFromReporter(ConfidentialCase $case, string $body): void
    {
        $case->addMessage($this->crypto->encrypt(trim($body), $this->key($case)), null);
        $this->em->flush();
        $this->notifyConfidants($case);
        $this->em->flush();
    }

    /**
     * Answer of a confidant; the person writing gets a notice without content if they left an email address.
     */
    public function replyFromConfidant(ConfidentialCase $case, User $author, string $body, ?string $slug): void
    {
        $key = $this->key($case);
        $case->addMessage($this->crypto->encrypt(trim($body), $key), $author);
        $case->markStaffRead();
        $this->em->flush();

        if (null === $case->getEmail() || null === $slug) {
            return;
        }
        $organization = $case->getOrganization()->getName();
        try {
            $this->mailer->send($this->crypto->decrypt($case->getEmail(), $key), 'confidential.email.subject', 'confidential_reply', [
                'organization' => $organization,
                'url' => $this->urls->generate('public_confidential', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL),
                'subject_params' => ['%organization%' => $organization],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Confidential reply notice failed: {message}', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Decrypted conversation for display.
     *
     * @return array{subject: string, messages: list<array{staff: bool, author: ?User, body: string, date: \DateTimeImmutable}>}
     */
    public function read(ConfidentialCase $case): array
    {
        $key = $this->key($case);
        $messages = [];
        foreach ($case->getMessages() as $message) {
            $messages[] = [
                'staff' => $message->isFromStaff(),
                'author' => $message->getAuthor(),
                'body' => $this->crypto->decrypt($message->getBody(), $key),
                'date' => $message->getCreatedOn(),
            ];
        }

        return ['subject' => $this->subject($case), 'messages' => $messages];
    }

    public function subject(ConfidentialCase $case): string
    {
        return $this->crypto->decrypt($case->getSubject(), $this->key($case));
    }

    private function key(ConfidentialCase $case): string
    {
        return $this->crypto->unwrapKey($case->getWrappedKey());
    }

    private function notifyConfidants(ConfidentialCase $case): void
    {
        $this->notifications->notify(
            $case->getOrganization()->getConfidants(),
            NotificationType::Confidential,
            $case->getOrganization()->getName(),
            $this->urls->generate('confidential_show', ['id' => $case->getId()]),
            null,
            [ConfidentialCaseVoter::VIEW, $case],
        );
    }
}
