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
 * Invitations (meetings, appointments) go out from the organization's own mailbox in the name of the inviting
 * person – one email per address, filed under "sent", answers arrive where they are dealt with. Guests without
 * an account never get them as system mail: they have not signed up for mails from the platform.
 * If sending fails, the email stays under drafts with the error.
 */
final readonly class OrganizationMailer
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
     * Sends the Markdown body with the attachment to every address (flushes).
     *
     * @param list<string>                                            $recipients
     * @param array{body: string, filename: string, mimeType: string} $attachment
     */
    public function send(?Organization $organization, User $sender, array $recipients, string $subject, string $body, array $attachment, ?Project $project = null): MailboxDelivery
    {
        if ([] === $recipients) {
            return new MailboxDelivery(0);
        }
        $account = $this->account($organization, $sender);
        if (null === $account) {
            return new MailboxDelivery(0, \count($recipients));
        }

        $signature = $this->assistant->signature($sender, $account)?->getBody() ?? '';
        $data = new ComposeData();
        $data->account = $account;
        $data->to = implode(', ', $recipients);
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
            $this->logger->error('Invitation from the organization mailbox failed: {error}', ['error' => $e->getMessage()]);
            if ($this->em->isOpen()) {
                $draft->unqueue($e->getMessage());
                $this->em->flush();
            }

            return new MailboxDelivery(0, \count($recipients), $account, $e->getMessage());
        }

        return new MailboxDelivery(\count($recipients), 0, $account);
    }
}
