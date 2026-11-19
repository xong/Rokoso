<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Draft;
use App\Entity\MailAccount;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\MailAccountRepository;
use App\Service\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Invitations to guests without an account go out from the organization's own mailbox – one email per guest,
 * filed under "sent" – and never as system mail: guests have not signed up for mails from the platform.
 * If sending fails, the email stays under drafts with the error.
 */
final readonly class GuestMailer
{
    public function __construct(
        private MailAccountRepository $accounts,
        private MailSender $sender,
        private ComposeAssistant $assistant,
        private AttachmentStorage $storage,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /** The first active mailbox of the organization the user may send from */
    public function account(?Organization $organization, User $user): ?MailAccount
    {
        if (null === $organization) {
            return null;
        }
        foreach ($this->accounts->findForUser($user) as $account) {
            if ($account->getOrganization() === $organization && $account->isEnabled()) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Sends the Markdown body with the attachment to every guest (flushes).
     *
     * @param list<string>                                            $guests
     * @param array{body: string, filename: string, mimeType: string} $attachment
     */
    public function send(?Organization $organization, User $sender, array $guests, string $subject, string $body, array $attachment, ?Project $project = null): GuestDelivery
    {
        if ([] === $guests) {
            return new GuestDelivery(0);
        }
        $account = $this->account($organization, $sender);
        if (null === $account) {
            return new GuestDelivery(0, \count($guests));
        }

        $signature = $this->assistant->signature($sender, $account)?->getBody() ?? '';
        $data = new ComposeData();
        $data->account = $account;
        $data->to = implode(', ', $guests);
        $data->subject = $subject;
        $data->body = '' === trim($signature) ? $body : $body."\n\n-- \n".$signature;
        $data->project = $project;
        $data->circular = true;
        $draft = new Draft($sender, $account)->apply($data);
        $draft->addFile($attachment['filename'], $attachment['mimeType'], \strlen($attachment['body']), $this->storage->store($attachment['body']));
        $this->em->persist($draft);

        try {
            $this->sender->send($draft);
        } catch (\Throwable $e) {
            $this->logger->error('Guest invitation failed: {error}', ['error' => $e->getMessage()]);
            if ($this->em->isOpen()) {
                $draft->unqueue($e->getMessage());
                $this->em->flush();
            }

            return new GuestDelivery(0, \count($guests), $account, $e->getMessage());
        }

        return new GuestDelivery(\count($guests), 0, $account);
    }
}
