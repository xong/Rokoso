<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Draft;
use App\Entity\Signature;
use App\Entity\User;
use App\Mail\ComposeAssistant;
use App\Mail\MailSender;
use App\Repository\DraftRepository;
use App\Repository\MailAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Own drafts, undo of a queued mail and personal signatures.
 */
#[Route('/mail')]
final class DraftController extends AbstractController
{
    #[Route('/drafts', name: 'mail_drafts', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, DraftRepository $drafts): Response
    {
        return $this->render('mail/drafts.html.twig', [
            'drafts' => $drafts->findOpenFor($user),
            'queued' => $drafts->findQueuedFor($user),
        ]);
    }

    #[Route('/drafts/{id<\d+>}/delete', name: 'mail_draft_delete', methods: ['POST'])]
    public function delete(Request $request, Draft $draft, #[CurrentUser] User $user, MailSender $sender, EntityManagerInterface $em): Response
    {
        $this->denyUnlessOwner($draft, $user);
        if ($this->isCsrfTokenValid('draft-delete', $request->getPayload()->getString('_token'))) {
            $sender->discard($draft);
            $em->flush();
            $this->addFlash('success', 'compose.draft_deleted');
        }

        return $this->redirectToRoute('mail_drafts');
    }

    /**
     * "Undo": stops a queued mail and opens it again for editing.
     */
    #[Route('/drafts/{id<\d+>}/cancel', name: 'mail_draft_cancel', methods: ['POST'])]
    public function cancel(Request $request, int $id, #[CurrentUser] User $user, DraftRepository $drafts, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('draft-cancel', $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('mail_drafts');
        }
        $draft = $drafts->find($id);
        if (null === $draft || !$draft->isQueued()) {
            $this->addFlash('error', 'compose.cancel_too_late');

            return $this->redirectToRoute('mail_sent');
        }
        $this->denyUnlessOwner($draft, $user);
        $draft->unqueue();
        $em->flush();
        $this->addFlash('info', 'compose.cancelled');

        return $this->redirectToRoute('mail_compose', ['draft' => $draft->getId()]);
    }

    /**
     * Called by the page when the undo time is over; the due mails go out after the response (OutboxListener).
     */
    #[Route('/outbox/flush', name: 'mail_outbox_flush', methods: ['POST'])]
    public function flush(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/signatures', name: 'mail_signatures', methods: ['GET', 'POST'])]
    public function signatures(Request $request, #[CurrentUser] User $user, MailAccountRepository $accounts, ComposeAssistant $assistant, EntityManagerInterface $em): Response
    {
        $available = $accounts->findForUser($user);
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('signatures', $request->getPayload()->getString('_token'))) {
            /** @var array<string, mixed> $bodies */
            $bodies = $request->getPayload()->all('signature');
            foreach ($available as $account) {
                $body = $bodies[(string) $account->getId()] ?? '';
                $body = \is_string($body) ? mb_substr($body, 0, 5000) : '';
                $signature = $assistant->signature($user, $account);
                if ('' === trim($body)) {
                    if (null !== $signature) {
                        $em->remove($signature);
                    }
                    continue;
                }
                if (null === $signature) {
                    $signature = new Signature($user, $account);
                    $em->persist($signature);
                }
                $signature->setBody($body);
            }
            $em->flush();
            $this->addFlash('success', 'signature.saved');

            return $this->redirectToRoute('mail_signatures');
        }

        return $this->render('mail/signatures.html.twig', [
            'accounts' => $available,
            'signatures' => $assistant->signatures($user, $available),
        ]);
    }

    private function denyUnlessOwner(Draft $draft, User $user): void
    {
        if ($draft->getOwner() !== $user) {
            throw $this->createAccessDeniedException();
        }
    }
}
