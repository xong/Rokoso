<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Attachment;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\MessageFolder;
use App\Service\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends an email via the account's SMTP server and files it under "sent".
 */
final readonly class MailSender
{
    public function __construct(
        private SmtpTransportFactory $transports,
        private AttachmentStorage $storage,
        private ReadTracker $readTracker,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param list<UploadedFile> $files
     */
    public function send(ComposeData $data, User $author, array $files = []): Message
    {
        $account = $data->account ?? throw new \LogicException('Account required.');
        $from = new Address($account->getEmailAddress(), $account->getSenderName() ?? '');
        $to = array_map(Address::create(...), ComposeData::splitAddresses($data->to));
        $cc = array_map(Address::create(...), ComposeData::splitAddresses($data->cc));
        $bcc = array_map(Address::create(...), ComposeData::splitAddresses($data->bcc));

        $email = (new Email())
            ->from($from)
            ->to(...$to)
            ->subject($data->subject)
            ->text($data->body);
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
            ->setBody($data->body)
            ->setProject($data->project)
            ->setInReplyTo($data->forward ? null : $original?->getMessageIdHeader());

        foreach ($files as $file) {
            $content = (string) file_get_contents($file->getPathname());
            $name = $file->getClientOriginalName();
            $mime = $file->getClientMimeType();
            $email->attach($content, $name, $mime);
            $message->addAttachment(new Attachment($message, $name, $mime, \strlen($content), $this->storage->store($content)));
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

        $this->em->persist($message);
        $this->em->flush();
        $this->readTracker->markRead($message, $author);

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
