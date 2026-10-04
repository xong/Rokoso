<?php

declare(strict_types=1);

namespace App\Participation;

use App\Entity\Attachment;
use App\Entity\CalendarItem;
use App\Entity\Contact;
use App\Entity\ContactGroup;
use App\Entity\EventSignup;
use App\Entity\Message;
use App\Entity\PublicRequest;
use App\Entity\PublicSettings;
use App\Entity\PublicTopic;
use App\Enum\MessageFolder;
use App\Enum\PublicRequestKind;
use App\Notification\ActivityNotifier;
use App\Service\AttachmentStorage;
use App\Service\SystemMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns public submissions into internal data: contact form → inbox message, event signup, group subscription.
 * With email confirmation switched on (always for subscriptions) a PublicRequest is stored first.
 */
final readonly class PublicSubmissionHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttachmentStorage $storage,
        private SystemMailer $mailer,
        private ActivityNotifier $notifier,
        private UrlGeneratorInterface $urls,
        private UriSigner $signer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array{name: string, email: string, institution?: ?string, topic?: ?PublicTopic, subject: string, message: string, attachments?: list<UploadedFile>, copy?: bool} $data
     *
     * @return bool true when the sender must confirm by email first
     */
    public function contact(PublicSettings $settings, array $data): bool
    {
        $files = [];
        foreach ($data['attachments'] ?? [] as $file) {
            $content = (string) file_get_contents($file->getPathname());
            $files[] = [
                'filename' => $file->getClientOriginalName(),
                'mimeType' => $file->getClientMimeType(),
                'size' => \strlen($content),
                'path' => $this->storage->store($content),
            ];
        }
        $payload = [
            'name' => $data['name'],
            'institution' => $data['institution'] ?? null,
            'topic' => ($data['topic'] ?? null)?->getId(),
            'subject' => $data['subject'],
            'message' => $data['message'],
            'attachments' => $files,
            'copy' => $data['copy'] ?? false,
        ];

        if ($settings->isSpamConfirmEmail()) {
            $this->request($settings, PublicRequestKind::Contact, $data['email'], $payload);

            return true;
        }
        $this->deliverContact($settings, $data['email'], $payload);

        return false;
    }

    public const string SIGNUP_DONE = 'done';
    public const string SIGNUP_CONFIRM = 'confirm';
    public const string SIGNUP_FULL = 'full';

    /**
     * @param array{name: string, email: string, persons: int} $data
     *
     * @return self::SIGNUP_* outcome
     */
    public function signup(PublicSettings $settings, CalendarItem $item, array $data): string
    {
        $payload = ['item' => $item->getId(), 'name' => $data['name'], 'persons' => $data['persons']];
        $free = $item->getSignupFree();
        if (null !== $free && $data['persons'] > $free) {
            return self::SIGNUP_FULL;
        }
        if ($settings->isSpamConfirmEmail()) {
            $this->request($settings, PublicRequestKind::Signup, $data['email'], $payload);

            return self::SIGNUP_CONFIRM;
        }

        return $this->deliverSignup($item, $data['email'], $payload) ? self::SIGNUP_DONE : self::SIGNUP_FULL;
    }

    /**
     * Subscriptions always need confirmation (double opt-in).
     *
     * @param array{name: string, email: string, groups: iterable<ContactGroup>} $data
     */
    public function subscribe(PublicSettings $settings, array $data): void
    {
        $groups = [];
        foreach ($data['groups'] as $group) {
            $groups[] = (int) $group->getId();
        }
        $this->request($settings, PublicRequestKind::Subscribe, $data['email'], ['name' => $data['name'], 'groups' => $groups]);
    }

    /**
     * Carries out a confirmed request. Returns false when it can no longer be carried out (e.g. event full).
     */
    public function confirm(PublicRequest $request): bool
    {
        $settings = $this->em->getRepository(PublicSettings::class)->findOneBy(['organization' => $request->getOrganization()]);
        $payload = $request->getPayload();
        $done = false;
        if (null !== $settings) {
            $done = match ($request->getKind()) {
                PublicRequestKind::Contact => null !== $this->deliverContact($settings, $request->getEmail(), $payload),
                PublicRequestKind::Signup => $this->confirmSignup($request->getEmail(), $payload),
                PublicRequestKind::Subscribe => $this->deliverSubscribe($settings, $request->getEmail(), $payload),
            };
        }
        $this->em->remove($request);
        $this->em->flush();

        return $done;
    }

    /** Signed link that removes the address from the group */
    public function unsubscribeUrl(ContactGroup $group, string $email, string $slug): string
    {
        return $this->signer->sign($this->urls->generate('public_unsubscribe', [
            'slug' => $slug, 'group' => $group->getId(), 'email' => mb_strtolower($email),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function unsubscribe(ContactGroup $group, string $email): bool
    {
        foreach ($group->getContacts() as $contact) {
            if (0 === strcasecmp((string) $contact->getEmail(), $email)) {
                $contact->removeGroup($group);
                $this->em->flush();

                return true;
            }
        }

        return false;
    }

    /** Removes expired confirmation requests including stored attachments; returns the number removed */
    public function purgeExpired(): int
    {
        $count = 0;
        foreach ($this->em->getRepository(PublicRequest::class)->findAll() as $request) {
            if (!$request->isExpired()) {
                continue;
            }
            $this->removeFiles($request->getPayload());
            $this->em->remove($request);
            ++$count;
        }
        $this->em->flush();
        $this->em->getRepository(PublicRequest::class)->purgeHits();

        return $count;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(PublicSettings $settings, PublicRequestKind $kind, string $email, array $payload): void
    {
        $request = new PublicRequest($settings->getOrganization(), $kind, $email, $payload);
        $this->em->persist($request);
        $this->em->flush();

        $this->mailer->send($email, 'public.email.confirm_subject', 'public_confirm', [
            'organization' => $settings->getOrganization()->getName(),
            'kind' => $kind->value,
            'url' => $this->urls->generate('public_confirm', ['token' => $request->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            'subject_params' => ['%organization%' => $settings->getOrganization()->getName()],
        ], \is_string($payload['name'] ?? null) ? $payload['name'] : null);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function deliverContact(PublicSettings $settings, string $email, array $payload): ?Message
    {
        $account = $settings->getContactAccount();
        if (null === $account) {
            $this->removeFiles($payload);

            return null;
        }
        $topic = \is_int($payload['topic'] ?? null) ? $this->em->find(PublicTopic::class, $payload['topic']) : null;
        if (null !== $topic && $topic->getSettings() !== $settings) {
            $topic = null;
        }

        $name = (string) ($payload['name'] ?? '');
        $lines = [];
        if (null !== $topic) {
            $lines[] = $this->translator->trans('public.contact.topic').': '.$topic->getName();
        }
        if (\is_string($payload['institution'] ?? null) && '' !== $payload['institution']) {
            $lines[] = $this->translator->trans('public.contact.institution').': '.$payload['institution'];
        }
        $body = ([] === $lines ? '' : implode("\n", $lines)."\n\n").$payload['message'];

        $domain = substr((string) strrchr($account->getEmailAddress(), '@'), 1) ?: 'rokoso.local';
        $message = (new Message())
            ->setMailAccount($account)
            ->setFolder(MessageFolder::Inbox)
            ->setMessageIdHeader('<public-'.bin2hex(random_bytes(12)).'@'.$domain.'>')
            ->setFrom($email, $name)
            ->setToRecipients([['name' => $account->getName(), 'address' => $account->getEmailAddress()]])
            ->setSubject((string) $payload['subject'])
            ->setBody($body);
        $message->setThreadKey($message->deriveThreadKey());
        if (null !== $topic) {
            $message->setProject($topic->getProject());
            if (null !== $topic->getAssignee()) {
                $message->addAssignee($topic->getAssignee());
            }
        }
        foreach (\is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [] as $file) {
            $message->addAttachment(new Attachment($message, $file['filename'], $file['mimeType'], $file['size'], $file['path']));
        }

        $this->em->persist($message);
        $this->em->flush();
        if (!$message->getAssignees()->isEmpty()) {
            $this->notifier->messageAssigned($message, $message->getAssignees(), null);
            $this->em->flush();
        }

        if (true === ($payload['copy'] ?? false)) {
            $this->mailer->send($email, 'public.email.copy_subject', 'public_copy', [
                'organization' => $settings->getOrganization()->getName(),
                'message_subject' => $message->getSubject(),
                'body' => $body,
                'subject_params' => ['%organization%' => $settings->getOrganization()->getName()],
            ], $name, [], $account->getEmailAddress());
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function confirmSignup(string $email, array $payload): bool
    {
        $item = \is_int($payload['item'] ?? null) ? $this->em->find(CalendarItem::class, $payload['item']) : null;

        return null !== $item && $this->deliverSignup($item, $email, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function deliverSignup(CalendarItem $item, string $email, array $payload): bool
    {
        $persons = max(1, (int) ($payload['persons'] ?? 1));
        $free = $item->getSignupFree();
        if (!$item->isSignupOpen() || (null !== $free && $persons > $free)) {
            return false;
        }
        $signup = new EventSignup($item, (string) $payload['name'], $email, $persons);
        $item->getSignups()->add($signup);
        $this->em->persist($signup);
        $this->em->flush();

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function deliverSubscribe(PublicSettings $settings, string $email, array $payload): bool
    {
        $organization = $settings->getOrganization();
        $groups = [];
        foreach (\is_array($payload['groups'] ?? null) ? $payload['groups'] : [] as $id) {
            $group = $this->em->find(ContactGroup::class, $id);
            if (null !== $group && $group->getOrganization() === $organization && $group->isPublicSubscribe()) {
                $groups[] = $group;
            }
        }
        $creator = $organization->getAdmins()[0] ?? null;
        if ([] === $groups || null === $creator) {
            return false;
        }

        $contact = $this->em->getRepository(Contact::class)->findOneBy(['organization' => $organization, 'email' => $email]);
        if (null === $contact) {
            $name = trim((string) ($payload['name'] ?? ''));
            $parts = explode(' ', $name);
            $last = array_pop($parts);
            $contact = (new Contact($creator))
                ->setOrganization($organization)
                ->setFirstName([] === $parts ? null : implode(' ', $parts))
                ->setLastName('' === $last ? null : $last)
                ->setEmail($email)
                ->setNotes($this->translator->trans('public.subscribe.contact_note'));
            $this->em->persist($contact);
        }
        $links = [];
        foreach ($groups as $group) {
            $contact->addGroup($group);
            $links[] = ['name' => $group->getName(), 'url' => $this->unsubscribeUrl($group, $email, (string) $settings->getSlug())];
        }
        $this->em->flush();

        $this->mailer->send($email, 'public.email.subscribed_subject', 'public_subscribed', [
            'organization' => $organization->getName(),
            'groups' => $links,
            'subject_params' => ['%organization%' => $organization->getName()],
        ], \is_string($payload['name'] ?? null) ? $payload['name'] : null);

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function removeFiles(array $payload): void
    {
        foreach (\is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [] as $file) {
            $this->storage->remove($file['path']);
        }
    }
}
