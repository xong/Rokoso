<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\ShelfItem;
use App\Entity\User;
use App\Enum\MessageFolder;
use App\Form\ComposeFormType;
use App\Mail\ComposeData;
use App\Mail\MailSender;
use App\Repository\MailAccountRepository;
use App\Repository\MessageRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\MessageVoter;
use App\Service\Shelf;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * New email, reply, reply all and forward.
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
        MailSender $sender,
        Shelf $shelf,
        TranslatorInterface $translator,
        LoggerInterface $logger,
    ): Response {
        $available = array_values(array_filter($accounts->findForUser($user), static fn ($a): bool => $a->isEnabled()));
        if ([] === $available) {
            $this->addFlash('info', 'compose.no_account');

            return $this->redirectToRoute('organization_index');
        }

        $data = new ComposeData();
        $data->account = $available[0];
        $data->to = $request->query->getString('to');
        $originalId = $request->query->getInt('reply') ?: $request->query->getInt('forward');
        if ($originalId > 0) {
            $original = $messages->find($originalId);
            if (null === $original) {
                throw $this->createNotFoundException();
            }
            $this->denyAccessUnlessGranted(MessageVoter::VIEW, $original);
            $this->prefill($data, $original, $request->query->has('forward'), $request->query->getBoolean('all'), $available, $translator);
        }

        $shelfItems = $shelf->items($user);
        $preselected = array_map(intval(...), $request->query->all('shelf'));
        $data->shelfItems = array_values(array_filter($shelfItems, static fn (ShelfItem $i): bool => \in_array($i->getId(), $preselected, true)));

        $form = $this->createForm(ComposeFormType::class, $data, [
            'accounts' => $available,
            'projects' => $projects->findVisibleFor($user),
            'shelf' => $shelfItems,
            'forward_attachments' => $data->forward && null !== $data->original && $data->original->hasAttachments(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<UploadedFile> $files */
            $files = array_values(array_filter((array) $form->get('files')->getData(), static fn ($f): bool => $f instanceof UploadedFile));
            try {
                $message = $sender->send($data, $user, $files);
                $this->addFlash('success', 'compose.sent');

                return $this->redirectToRoute('mail_show', ['folder' => 'sent', 'id' => $message->getId()]);
            } catch (\Throwable $e) {
                $logger->error('Sending mail failed: {error}', ['error' => $e->getMessage()]);
                $form->addError(new FormError($translator->trans('compose.failed', ['%error%' => $e->getMessage()])));
            }
        }

        return $this->render('compose/form.html.twig', [
            'form' => $form,
            'original' => $data->original,
            'is_forward' => $data->forward,
        ]);
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
