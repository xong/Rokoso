<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Draft;
use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\ShelfItem;
use App\Entity\User;
use App\Enum\MessageFolder;
use App\Form\ComposeFormType;
use App\Mail\ComposeAssistant;
use App\Mail\ComposeData;
use App\Mail\MailSender;
use App\Mail\Outbox;
use App\Repository\ContactGroupRepository;
use App\Repository\DraftRepository;
use App\Repository\MailAccountRepository;
use App\Repository\MessageRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\MessageVoter;
use App\Security\Voter\OrganizationVoter;
use App\Service\AttachmentStorage;
use App\Service\Shelf;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * New email, reply, reply all and forward. The form state is kept as a draft (saved automatically);
 * "send" queues the draft in the outbox for a few seconds (undo).
 */
final class ComposeController extends AbstractController
{
    #[Route('/mail/new', name: 'mail_compose')]
    public function compose(
        Request $request,
        #[CurrentUser] User $user,
        MailAccountRepository $accounts,
        MessageRepository $messages,
        ProjectRepository $projects,
        DraftRepository $drafts,
        ContactGroupRepository $groups,
        MailSender $sender,
        Outbox $outbox,
        ComposeAssistant $assistant,
        AttachmentStorage $storage,
        Shelf $shelf,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        LoggerInterface $logger,
    ): Response {
        $available = array_values(array_filter($accounts->findForUser($user), static fn ($a): bool => $a->isEnabled()));
        if ([] === $available) {
            $this->addFlash('info', 'compose.no_account');

            return $this->redirectToRoute('organization_index');
        }
        $signatures = $assistant->signatures($user, $available);

        $draft = $this->findDraft($request, $drafts, $user);
        if (null !== $draft) {
            if ($draft->isQueued()) {
                // opening a waiting mail stops sending it
                $draft->unqueue();
                $em->flush();
            }
            $data = $draft->toComposeData();
            if (!\in_array($data->account, $available, true)) {
                $data->account = $available[0];
            }
            if (null !== $data->original && !$this->isGranted(MessageVoter::VIEW, $data->original)) {
                $data->original = null;
            }
        } else {
            $data = new ComposeData();
            $data->account = $available[0];
            $data->to = $request->query->getString('to');
            if ($request->query->getInt('group') > 0) {
                $group = $groups->find($request->query->getInt('group'));
                if (null === $group) {
                    throw $this->createNotFoundException();
                }
                $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $group->getOrganization());
                $data->to = implode(', ', $group->getAddresses());
                $data->circular = true;
            }
            $originalId = $request->query->getInt('reply') ?: $request->query->getInt('forward');
            if ($originalId > 0) {
                $original = $messages->find($originalId);
                if (null === $original) {
                    throw $this->createNotFoundException();
                }
                $this->denyAccessUnlessGranted(MessageVoter::VIEW, $original);
                $this->prefill($data, $original, $request->query->has('forward'), $request->query->getBoolean('all'), $available, $translator);
            }
            $data->body = ComposeAssistant::withSignature($data->body, $signatures[$data->account?->getId() ?? 0] ?? '');
        }

        $shelfItems = $shelf->items($user);
        $preselected = array_map(intval(...), $request->query->all('shelf'));
        $data->shelfItems = array_values(array_filter($shelfItems, static fn (ShelfItem $i): bool => \in_array($i->getId(), $preselected, true)));

        $form = $this->createForm(ComposeFormType::class, $data, [
            'accounts' => $available,
            'projects' => $projects->findVisibleFor($user),
            'shelf' => $shelfItems,
            'forward_attachments' => $data->forward && null !== $data->original && $data->original->hasAttachments(),
            'draft_id' => $draft?->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $payload = $request->getPayload();
            $keep = array_values(array_map(intval(...), $payload->all('keep_files')));
            /** @var array<string, mixed> $fields */
            $fields = $payload->all('compose_form');
            // saving a draft needs no valid recipients, only a valid token
            $tokenValid = $this->isCsrfTokenValid('submit', \is_string($fields['_token'] ?? null) ? $fields['_token'] : '');
            if ($request->headers->has('X-Autosave')) {
                if (!$tokenValid) {
                    return new JsonResponse(null, Response::HTTP_FORBIDDEN);
                }
                $draft = $this->saveDraft($draft, $data, $user, $keep, $available[0], $storage, $em);

                return new JsonResponse(['id' => $draft->getId()]);
            }
            if ($payload->has('save_draft') && $tokenValid) {
                $draft = $this->saveDraft($draft, $data, $user, $keep, $available[0], $storage, $em);
                $sender->attach($draft, $this->uploadedFiles($form->get('files')->getData()), $data->shelfItems);
                $em->flush();
                $this->addFlash('success', 'compose.draft_saved');

                return $this->redirectToRoute('mail_drafts');
            }
            if (!$payload->has('save_draft') && $form->isValid()) {
                $draft = $this->saveDraft($draft, $data, $user, $keep, $available[0], $storage, $em);
                try {
                    $sender->attach($draft, $this->uploadedFiles($form->get('files')->getData()), $data->shelfItems);
                    $message = $outbox->queue($draft);
                    if (null !== $message) {
                        $this->addFlash('success', 'compose.sent');

                        return $this->redirectToRoute('mail_show', ['folder' => 'sent', 'id' => $message->getId()]);
                    }
                    $this->addFlash('outbox', ['id' => $draft->getId(), 'seconds' => $outbox->getDelay()]);

                    return null !== $data->original
                        ? $this->redirectToRoute('mail_show', ['folder' => MessageFolder::Sent === $data->original->getFolder() ? 'sent' : 'inbox', 'id' => $data->original->getId()])
                        : $this->redirectToRoute('mail_inbox');
                } catch (\Throwable $e) {
                    $logger->error('Sending mail failed: {error}', ['error' => $e->getMessage()]);
                    $form->addError(new FormError($translator->trans('compose.failed', ['%error%' => $e->getMessage()])));
                    if ($em->isOpen()) {
                        $draft->unqueue($e->getMessage());
                        $em->flush();
                    }
                }
            }
        }

        return $this->render('compose/form.html.twig', [
            'form' => $form,
            'draft' => $draft,
            'original' => $data->original,
            'is_forward' => $data->forward,
            'writers' => null !== $data->original ? $drafts->findOtherWriters($data->original, $user) : [],
            'recipients' => $assistant->recipients($user),
            'signatures' => $signatures,
            'snippets' => $assistant->snippets($user),
        ]);
    }

    /**
     * Own draft from ?draft= (opening) or from the hidden form field (autosave, sending).
     */
    private function findDraft(Request $request, DraftRepository $drafts, User $user): ?Draft
    {
        /** @var array<string, mixed> $fields */
        $fields = $request->request->all('compose_form');
        $id = $request->query->getInt('draft') ?: (int) (is_numeric($fields['draft'] ?? null) ? $fields['draft'] : 0);
        if ($id <= 0) {
            return null;
        }
        // null: sent in the meantime (other tab), start over
        $draft = $drafts->find($id);
        if (null !== $draft && $draft->getOwner() !== $user) {
            throw $this->createAccessDeniedException();
        }

        return $draft;
    }

    /**
     * @param list<int> $keep indexes of the already stored files to keep
     */
    private function saveDraft(?Draft $draft, ComposeData $data, User $user, array $keep, MailAccount $fallback, AttachmentStorage $storage, EntityManagerInterface $em): Draft
    {
        $draft ??= new Draft($user, $data->account ?? $fallback);
        $draft->apply($data);
        foreach ($draft->keepFiles($keep) as $path) {
            $storage->remove($path);
        }
        $em->persist($draft);
        $em->flush();

        return $draft;
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(mixed $files): array
    {
        return array_values(array_filter(\is_array($files) ? $files : [], static fn ($f): bool => $f instanceof UploadedFile));
    }

    /**
     * @param list<MailAccount> $available
     */
    private function prefill(ComposeData $data, Message $original, bool $forward, bool $all, array $available, TranslatorInterface $translator): void
    {
        $data->original = $original;
        $data->forward = $forward;
        $data->project = $original->getProject();
        foreach ($available as $account) {
            if ($account === $original->getMailAccount()) {
                $data->account = $account;
            }
        }

        $subject = $original->getSubject();
        $date = $original->getDate()->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i');
        $sender = '' !== $original->getFromName() ? \sprintf('%s <%s>', $original->getFromName(), $original->getFromAddress()) : $original->getFromAddress();
        $text = (string) ($original->getTextBody() ?? $original->getSnippet());

        if ($forward) {
            $data->subject = preg_match('/^(fwd?|wg):/i', $subject) ? $subject : 'Fwd: '.$subject;
            $data->body = "\n\n".$translator->trans('compose.forward_header', ['%from%' => $sender, '%date%' => $date, '%subject%' => $subject])."\n\n".$text;

            return;
        }

        $data->subject = preg_match('/^(re|aw):/i', $subject) ? $subject : 'Re: '.$subject;
        $quoted = implode("\n", array_map(static fn (string $line): string => '> '.$line, explode("\n", str_replace("\r\n", "\n", $text))));
        $data->body = "\n\n".$translator->trans('compose.reply_header', ['%from%' => $sender, '%date%' => $date])."\n".$quoted;

        if (MessageFolder::Sent === $original->getFolder()) {
            // Antwort auf eigene Mail: an die ursprünglichen Empfänger
            $data->to = implode(', ', array_map(static fn (array $r): string => $r['address'], $original->getToRecipients()));
        } else {
            $data->to = $original->getReplyToAddress() ?? $original->getFromAddress();
        }
        if ($all) {
            $own = array_map(static fn ($a): string => $a->getEmailAddress(), $available);
            $others = array_filter(
                array_map(static fn (array $r): string => $r['address'], [...$original->getToRecipients(), ...$original->getCcRecipients()]),
                static fn (string $address): bool => !\in_array($address, $own, true) && $address !== $data->to,
            );
            $data->cc = implode(', ', array_unique($others));
        }
    }
}
