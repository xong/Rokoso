<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Attachment;
use App\Entity\MailAccount;
use App\Entity\Message;
use App\Enum\MessageFolder;
use App\Repository\MessageRepository;
use App\Service\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Imports new messages of a mail account into the database.
 */
final readonly class MailSynchronizer
{
    public function __construct(
        private MailboxReader $reader,
        private MessageParser $parser,
        private MessageRepository $messages,
        private AttachmentStorage $storage,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int number of imported messages
     */
    public function sync(MailAccount $account, int $limit = 200): int
    {
        try {
            $result = $this->reader->fetchNew($account, $limit);
        } catch (\Throwable $e) {
            $this->logger->warning('Mail sync failed for account {id}: {error}', ['id' => $account->getId(), 'error' => $e->getMessage()]);
            $account->markSynced(mb_substr($e->getMessage(), 0, 1000));
            $this->em->flush();

            return 0;
        }

        $imported = 0;
        $lastUid = $account->getUidValidity() === $result->uidValidity ? $account->getLastUid() : null;
        foreach ($result->messages as $uid => $raw) {
            $lastUid = max($lastUid ?? 0, $uid);
            try {
                if ($this->import($account, $uid, $raw)) {
                    ++$imported;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Could not import message {uid} of account {id}: {error}', ['uid' => $uid, 'id' => $account->getId(), 'error' => $e->getMessage()]);
            }
        }

        $account->setSyncPosition($result->uidValidity, $lastUid);
        $account->markSynced();
        $this->em->flush();

        return $imported;
    }

    private function import(MailAccount $account, int $uid, string $raw): bool
    {
        $parsed = $this->parser->parse($raw);
        if ($this->messages->existsForAccount((int) $account->getId(), $parsed->messageId, $uid)) {
            return false;
        }

        $message = (new Message())
            ->setMailAccount($account)
            ->setFolder(MessageFolder::Inbox)
            ->setImapUid($uid)
            ->setMessageIdHeader($parsed->messageId)
            ->setInReplyTo($parsed->inReplyTo)
            ->setReferencesHeader($parsed->references)
            ->setFrom($parsed->fromAddress, $parsed->fromName)
            ->setReplyToAddress($parsed->replyTo)
            ->setToRecipients($parsed->to)
            ->setCcRecipients($parsed->cc)
            ->setSubject($parsed->subject)
            ->setDate($parsed->date)
            ->setBody($parsed->text, $parsed->html);

        foreach ($parsed->attachments as $file) {
            $message->addAttachment(new Attachment(
                $message,
                $file['filename'],
                $file['mimeType'],
                \strlen($file['content']),
                $this->storage->store($file['content']),
                null === $file['contentId'] ? null : trim($file['contentId'], '<>'),
            ));
        }

        $this->em->persist($message);
        $this->em->flush();

        return true;
    }
}
