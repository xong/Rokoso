<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Attachment;
use App\Entity\Draft;
use App\Entity\Message;
use App\Entity\ShelfItem;
use App\Entity\StoredFile;
use App\Enum\MessageEventType;
use App\Enum\MessageFolder;
use App\Service\AttachmentStorage;
use App\Service\FileStorage;
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
        private FileStorage $files,
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
     * @param list<StoredFile>   $storedFiles
     */
    public function attach(Draft $draft, array $files, array $shelfItems, array $storedFiles = []): void
    {
        foreach ($files as $file) {
            $content = (string) file_get_contents($file->getPathname());
            $draft->addFile($file->getClientOriginalName(), $file->getClientMimeType(), \strlen($content), $this->storage->store($content));
        }
        foreach ($shelfItems as $item) {
            $content = $this->shelf->read($item);
            $draft->addFile($item->getFilename(), $item->getMimeType(), \strlen($content), $this->storage->store($content));
        }
        foreach ($storedFiles as $file) {
            $content = (string) file_get_contents($this->files->absolutePath($file->getStoragePath()));
            $draft->addFile($file->getFilename(), $file->getMimeType(), \strlen($content), $this->storage->store($content));
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
     * A circular letter goes out as one email per "to" address; it fails only if no email could be sent.
     */
    public function send(Draft $draft): Message
    {
        $data = $draft->toComposeData();
        $author = $draft->getOwner();
        $account = $draft->getAccount();
        $to = array_map(Address::create(...), ComposeData::splitAddresses($data->to));
        $cc = array_map(Address::create(...), ComposeData::splitAddresses($data->cc));
        $bcc = array_map(Address::create(...), ComposeData::splitAddresses($data->bcc));
        $html = '<div style="font-family: sans-serif; font-size: 14px; line-height: 1.5">'.$this->markdown->renderEmail($data->body).'</div>';
        $original = $data->original;
        $isReply = null !== $original && !$data->forward && null !== $original->getMessageIdHeader();

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
            ->setCircular($data->circular)
            ->setInReplyTo($isReply ? $original->getMessageIdHeader() : null);
        if ($isReply) {
            $message->setReferencesHeader(trim(($original->getReferencesHeader() ?? '').' <'.$original->getMessageIdHeader().'>'));
        }

        $files = [];
        foreach ($draft->getFiles() as $file) {
            $files[] = [$this->storage->read($file['path']), $file['name'], $file['mime']];
            $message->addAttachment(new Attachment($message, $file['name'], $file['mime'], $file['size'], $file['path']));
        }
        if (null !== $original && $data->forward && $data->keepAttachments) {
            foreach ($original->getVisibleAttachments() as $attachment) {
                $content = $this->storage->read($attachment->getStoragePath());
                $files[] = [$content, $attachment->getFilename(), $attachment->getMimeType()];
                $message->addAttachment(new Attachment($message, $attachment->getFilename(), $attachment->getMimeType(), \strlen($content), $this->storage->store($content)));
            }
        }

        $transport = $this->transports->create($account);
        $first = null;
        $failed = [];
        $error = null;
        foreach ($data->circular ? array_map(static fn (Address $a): array => [$a], $to) : [$to] as $recipients) {
            $email = (new Email())
                ->from(new Address($account->getEmailAddress(), $account->getSenderName() ?? ''))
                ->to(...$recipients)
                ->subject($data->subject)
                ->text($data->body)
                ->html($html);
            if (!$data->circular && [] !== $cc) {
                $email->cc(...$cc);
            }
            if (!$data->circular && [] !== $bcc) {
                $email->bcc(...$bcc);
            }
            if ($isReply) {
                $email->getHeaders()->addIdHeader('In-Reply-To', $original->getMessageIdHeader());
                $email->getHeaders()->addTextHeader('References', trim(($original->getReferencesHeader() ?? '').' <'.$original->getMessageIdHeader().'>'));
            }
            foreach ($files as [$content, $name, $mime]) {
                $email->attach($content, $name, $mime);
            }
            try {
                $sent = $transport->send($email);
                $first ??= $sent;
            } catch (\Throwable $e) {
                if (!$data->circular) {
                    throw $e;
                }
                $error ??= $e;
                $failed[] = $recipients[0]->getAddress();
                $this->logger->error('Circular to {address} failed: {error}', ['address' => $recipients[0]->getAddress(), 'error' => $e->getMessage()]);
            }
        }
        if (null !== $error && \count($failed) === \count($to)) {
            throw $error;
        }

        $message->setMessageIdHeader($first?->getMessageId());
        $message->setThreadKey(null !== $original && !$data->forward ? ($original->getThreadKey() ?? $message->deriveThreadKey()) : $message->deriveThreadKey());
        if ([] !== $failed) {
            $message->log(MessageEventType::SendFailed, $author, implode(', ', $failed));
        }
        $original?->log($data->forward ? MessageEventType::Forwarded : MessageEventType::Replied, $author, $data->to);

        $this->em->persist($message);
        $this->em->remove($draft);
        $this->em->flush();
        $this->readTracker->markRead($message, $author);

        if (null !== $account->getSentFolder() && null !== $first) {
            try {
                $this->sentFolder->append($account, $first->toString());
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
