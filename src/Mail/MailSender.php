<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Attachment;
use App\Entity\Draft;
use App\Entity\Message;
use App\Entity\ShelfItem;
use App\Enum\MessageEventType;
use App\Enum\MessageFolder;
use App\Service\AttachmentStorage;
use App\Service\MarkdownRenderer;
use App\Service\Shelf;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends a draft via the account's SMTP server (Markdown body as HTML + text), files it under "sent"
 * and, if configured, copies it to the sent folder on the IMAP server.
 */
final readonly class MailSender
{
    public function __construct(
        private SmtpTransportFactory $transports,
        private AttachmentStorage $storage,
        private Shelf $shelf,
        private ReadTracker $readTracker,
        private MarkdownRenderer $markdown,
        private SentFolderWriter $sentFolder,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Stores uploaded files and files from the shelf with the draft, so it can be sent later.
     *
     * @param list<UploadedFile> $files
     * @param list<ShelfItem>    $shelfItems
     */
    public function attach(Draft $draft, array $files, array $shelfItems): void
    {
        foreach ($files as $file) {
            $content = (string) file_get_contents($file->getPathname());
            $draft->addFile($file->getClientOriginalName(), $file->getClientMimeType(), \strlen($content), $this->storage->store($content));
        }
        foreach ($shelfItems as $item) {
            $content = $this->shelf->read($item);
            $draft->addFile($item->getFilename(), $item->getMimeType(), \strlen($content), $this->storage->store($content));
        }
    }

    /**
     * Deletes a draft together with its stored files (not yet sent).
     */
    public function discard(Draft $draft): void
    {
        foreach ($draft->getFiles() as $file) {
            $this->storage->remove($file['path']);
        }
        $this->em->remove($draft);
    }

    /**
     * Sends the draft and replaces it by the sent message (flushes).
     */
    public function send(Draft $draft): Message
    {
        $data = $draft->toComposeData();
        $author = $draft->getOwner();
        $account = $draft->getAccount();
        $from = new Address($account->getEmailAddress(), $account->getSenderName() ?? '');
        $to = array_map(Address::create(...), ComposeData::splitAddresses($data->to));
        $cc = array_map(Address::create(...), ComposeData::splitAddresses($data->cc));
        $bcc = array_map(Address::create(...), ComposeData::splitAddresses($data->bcc));

        $html = '<div style="font-family: sans-serif; font-size: 14px; line-height: 1.5">'.$this->markdown->renderEmail($data->body).'</div>';
        $email = (new Email())
            ->from($from)
            ->to(...$to)
            ->subject($data->subject)
            ->text($data->body)
            ->html($html);
        if ([] !== $cc) {
            $email->cc(...$cc);
        }
        if ([] !== $bcc) {
            $email->bcc(...$bcc);
        }

        $original = $data->original;
        if (null !== $original && !$data->forward && null !== $original->getMessageIdHeader()) {
            $id = '<'.$original->getMessageIdHeader().'>';
            $email->getHeaders()->addIdHeader('In-Reply-To', $original->getMessageIdHeader());
            $email->getHeaders()->addTextHeader('References', trim(($original->getReferencesHeader() ?? '').' '.$id));
        }

        $message = (new Message())
            ->setMailAccount($account)
            ->setFolder(MessageFolder::Sent)
            ->setAuthor($author)
            ->setFrom($account->getEmailAddress(), $account->getSenderName() ?? '')
            ->setToRecipients(self::recipients($to))
            ->setCcRecipients(self::recipients($cc))
            ->setSubject($data->subject)
            ->setBody($data->body, $html)
            ->setProject($data->project)
            ->setInReplyTo($data->forward ? null : $original?->getMessageIdHeader());
        if (null !== $original && !$data->forward && null !== $original->getMessageIdHeader()) {
            $message->setReferencesHeader(trim(($original->getReferencesHeader() ?? '').' <'.$original->getMessageIdHeader().'>'));
        }

        foreach ($draft->getFiles() as $file) {
            $email->attach($this->storage->read($file['path']), $file['name'], $file['mime']);
            $message->addAttachment(new Attachment($message, $file['name'], $file['mime'], $file['size'], $file['path']));
        }
        if (null !== $original && $data->forward && $data->keepAttachments) {
            foreach ($original->getVisibleAttachments() as $attachment) {
                $content = $this->storage->read($attachment->getStoragePath());
                $email->attach($content, $attachment->getFilename(), $attachment->getMimeType());
                $message->addAttachment(new Attachment($message, $attachment->getFilename(), $attachment->getMimeType(), \strlen($content), $this->storage->store($content)));
            }
        }

        $sent = $this->transports->create($account)->send($email);
        $message->setMessageIdHeader($sent?->getMessageId());
        $message->setThreadKey(null !== $original && !$data->forward ? ($original->getThreadKey() ?? $message->deriveThreadKey()) : $message->deriveThreadKey());
        $original?->log($data->forward ? MessageEventType::Forwarded : MessageEventType::Replied, $author, $data->to);

        $this->em->persist($message);
        $this->em->remove($draft);
        $this->em->flush();
        $this->readTracker->markRead($message, $author);

        if (null !== $account->getSentFolder() && null !== $sent) {
            try {
                $this->sentFolder->append($account, $sent->toString());
            } catch (\Throwable $e) {
                $this->logger->warning('Copy to sent folder failed: {error}', ['error' => $e->getMessage()]);
            }
        }

        return $message;
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<array{name: string, address: string}>
     */
    private static function recipients(array $addresses): array
    {
        return array_map(static fn (Address $a): array => ['name' => $a->getName(), 'address' => mb_strtolower($a->getAddress())], $addresses);
    }
}
