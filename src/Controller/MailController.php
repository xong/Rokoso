<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Comment;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\MessageEventType;
use App\Mail\MailSynchronizer;
use App\Mail\MessageActions;
use App\Mail\MessageFilter;
use App\Mail\MessageHtmlRenderer;
use App\Mail\ParticipantResolver;
use App\Mail\ReadTracker;
use App\Repository\MailAccountRepository;
use App\Repository\MessageRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\MessageVoter;
use App\Service\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mail')]
final class MailController extends AbstractController
{
    public const array FOLDERS = ['inbox', 'all', 'sent', 'done', 'snoozed', 'trash'];

    public function __construct(
        private readonly MessageRepository $messages,
        private readonly MailAccountRepository $accounts,
        private readonly ProjectRepository $projects,
        private readonly ReadTracker $readTracker,
        private readonly MessageActions $actions,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'mail_inbox', defaults: ['folder' => 'inbox'])]
    #[Route('/all', name: 'mail_all', defaults: ['folder' => 'all'])]
    #[Route('/sent', name: 'mail_sent', defaults: ['folder' => 'sent'])]
    #[Route('/done', name: 'mail_done', defaults: ['folder' => 'done'])]
    #[Route('/snoozed', name: 'mail_snoozed', defaults: ['folder' => 'snoozed'])]
    #[Route('/trash', name: 'mail_trash', defaults: ['folder' => 'trash'])]
    public function list(Request $request, string $folder, #[CurrentUser] User $user): Response
    {
        return $this->render('mail/index.html.twig', $this->listContext($request, $folder, $user));
    }

    #[Route('/{folder<inbox|all|sent|done|snoozed|trash>}/{id<\d+>}', name: 'mail_show')]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    public function show(Request $request, string $folder, Message $message, #[CurrentUser] User $user, MessageHtmlRenderer $renderer, ParticipantResolver $participants): Response
    {
        $request->attributes->set('_nav', 'mail_'.$folder);
        $this->readTracker->markRead($message, $user);

        $view = $request->query->getString('view');
        $showHtml = $message->hasHtml() && 'text' !== $view;

        return $this->render('mail/show.html.twig', $this->listContext($request, $folder, $user) + [
            'message' => $message,
            'show_html' => $showHtml,
            'has_external_images' => $message->hasHtml() && $renderer->hasExternalImages($message),
            'load_images' => $request->query->getBoolean('images'),
            'candidates' => $participants->candidates($message),
            'message_projects' => $participants->projects($message),
            'open' => $request->query->getString('open'),
            'thread' => $this->messages->findThread($message, $user),
        ]);
    }

    /**
     * Sanitized HTML body for the sandboxed iframe (strict CSP, no scripts).
     */
    #[Route('/{id<\d+>}/html', name: 'mail_html')]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    public function html(Request $request, Message $message, MessageHtmlRenderer $renderer, #[CurrentUser] User $user, #[Autowire(service: 'profiler')] ?Profiler $profiler): Response
    {
        // Otherwise the dev toolbar is injected into the mail document (its scripts are blocked by the CSP)
        $profiler?->disable();

        $response = new Response($renderer->render($message, $user->getTheme()));
        $response->headers->set('Content-Security-Policy', $renderer->contentSecurityPolicy($request->query->getBoolean('images')));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    #[Route('/{id<\d+>}/attachment/{attachment<\d+>}', name: 'mail_attachment')]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    public function attachment(Message $message, Attachment $attachment, AttachmentStorage $storage): BinaryFileResponse
    {
        if ($attachment->getMessage() !== $message) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($storage->absolutePath($attachment->getStoragePath()));
        // Immer als Download ausliefern: kein Rendern fremder Inhalte unter unserer Domain
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getFilename(), 'anhang');
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/{id<\d+>}/trash', name: 'mail_trash_message', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('mail-action')]
    public function trash(Request $request, Message $message, #[CurrentUser] User $user): Response
    {
        $action = $message->isTrashed() ? 'restore' : 'trash';
        $changed = $this->actions->apply($action, [$message], $user);
        $this->undoFlash('trash' === $action ? 'mail.trashed' : 'mail.restored', $action, $changed);

        return $this->redirectToList($request);
    }

    /**
     * Status actions on one or several messages (toolbar, bulk selection, undo).
     */
    #[Route('/action', name: 'mail_action', methods: ['POST'])]
    #[IsCsrfTokenValid('mail-action')]
    public function action(Request $request, #[CurrentUser] User $user): Response
    {
        $payload = $request->getPayload();
        $action = $payload->getString('action');
        if (!\in_array($action, MessageActions::ACTIONS, true)) {
            throw $this->createNotFoundException();
        }
        $selected = [];
        foreach (\array_slice(array_unique(array_map(intval(...), $payload->all('ids'))), 0, 500) as $id) {
            $message = $this->messages->find($id);
            if (null !== $message && $this->isGranted(MessageVoter::VIEW, $message)) {
                $selected[] = $message;
            }
        }
        if ([] === $selected) {
            $this->addFlash('info', 'mail.bulk.none');

            return $this->redirectAfterAction($request, null);
        }

        $projectId = $payload->getInt('project') ?: null;
        $previousProject = $selected[0]->getProject()?->getId();
        $samePrevious = [] === array_filter($selected, static fn (Message $m): bool => $m->getProject()?->getId() !== $previousProject);
        $changed = $this->actions->apply($action, $selected, $user, $projectId, $this->snoozeUntil($payload->getString('until') ?: $payload->getString('until_custom')));

        if ($payload->getBoolean('undo')) {
            $this->addFlash('success', 'mail.undone');
        } elseif ('project' === $action) {
            $this->undoFlash(null === $projectId ? 'mail.project.removed' : 'mail.project.set', 'project', $samePrevious ? $changed : [], $previousProject);
        } else {
            $this->undoFlash('mail.action_done.'.$action, $action, $changed);
        }

        return $this->redirectAfterAction($request, 1 === \count($selected) ? $selected[0] : null);
    }

    #[Route('/{id<\d+>}/unread', name: 'mail_unread', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('mail-action')]
    public function unread(Request $request, Message $message, #[CurrentUser] User $user): Response
    {
        $this->readTracker->markUnread($message, $user);

        return $this->redirectToList($request);
    }

    #[Route('/{id<\d+>}/comment', name: 'mail_comment', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('mail-comment')]
    public function comment(Request $request, Message $message, #[CurrentUser] User $user): Response
    {
        $comment = Comment::onMessage($message, $user)->setBody($request->getPayload()->getString('body'));
        if ('' !== $comment->getBody()) {
            $this->em->persist($comment);
            $this->em->flush();
        }

        return $this->redirectToMessage($request, $message, 'comments');
    }

    #[Route('/{id<\d+>}/comment/{comment<\d+>}/delete', name: 'mail_comment_delete', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('mail-comment')]
    public function deleteComment(Request $request, Message $message, Comment $comment, #[CurrentUser] User $user): Response
    {
        if ($comment->getMessage() !== $message || $comment->getAuthor() !== $user) {
            throw $this->createAccessDeniedException();
        }
        $this->em->remove($comment);
        $this->em->flush();

        return $this->redirectToMessage($request, $message, 'comments');
    }

    /**
     * Replace the assignees (searchable multiselect).
     */
    #[Route('/{id<\d+>}/assignees', name: 'mail_assignees', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('mail-action')]
    public function assignees(Request $request, Message $message, ParticipantResolver $participants, #[CurrentUser] User $user): Response
    {
        $ids = array_map(intval(...), $request->getPayload()->all('assignees'));
        $candidates = $participants->candidates($message);
        foreach ($message->getAssignees()->toArray() as $assignee) {
            if (!\in_array($assignee->getId(), $ids, true)) {
                $message->removeAssignee($assignee)->log(MessageEventType::Unassigned, $user, $assignee->getName());
            }
        }
        foreach ($candidates as $candidate) {
            if (\in_array($candidate->getId(), $ids, true) && !$message->isAssignedTo($candidate)) {
                $message->addAssignee($candidate)->log(MessageEventType::Assigned, $user, $candidate->getName());
            }
        }
        $this->em->flush();

        return $this->redirectBack($request, $message);
    }

    /**
     * "Mir zuordnen" – toggles the current user as assignee.
     */
    #[Route('/{id<\d+>}/assign-me', name: 'mail_assign_me', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('mail-action')]
    public function assignMe(Request $request, Message $message, #[CurrentUser] User $user): Response
    {
        $this->actions->apply($message->isAssignedTo($user) ? 'unassign_me' : 'assign_me', [$message], $user);

        return $this->redirectBack($request, $message);
    }

    #[Route('/{id<\d+>}/project', name: 'mail_project', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('mail-action')]
    public function project(Request $request, Message $message, #[CurrentUser] User $user): Response
    {
        // The project no longer moves the message out of the inbox (Entscheidung 39).
        $previous = $message->getProject()?->getId();
        $projectId = $request->getPayload()->getInt('project') ?: null;
        $changed = $this->actions->apply('project', [$message], $user, $projectId);
        $this->undoFlash(null === $message->getProject() ? 'mail.project.removed' : 'mail.project.set', 'project', $changed, $previous);

        return $this->redirectBack($request, $message);
    }

    /**
     * Fetch new mails of the user's accounts now (otherwise done by cron).
     */
    #[Route('/sync', name: 'mail_sync', methods: ['POST'])]
    #[IsCsrfTokenValid('mail-sync')]
    public function sync(#[CurrentUser] User $user, MailSynchronizer $synchronizer): Response
    {
        $count = 0;
        $failed = false;
        foreach ($this->accounts->findForUser($user) as $account) {
            if ($account->isEnabled()) {
                $count += $synchronizer->sync($account, 50);
                $failed = $failed || null !== $account->getLastSyncError();
            }
        }
        $this->addFlash($failed ? 'error' : 'success', $failed ? 'mail.sync_failed' : 'mail.synced');

        return $this->redirectToRoute('mail_inbox');
    }

    /**
     * @return array<string, mixed>
     */
    private function listContext(Request $request, string $folder, User $user): array
    {
        $filter = MessageFilter::fromRequest($request, $folder);
        $messages = $this->messages->findForList($user, $filter);
        $hasMore = \count($messages) > MessageRepository::PAGE_SIZE;
        $messages = \array_slice($messages, 0, MessageRepository::PAGE_SIZE);

        return [
            'folder' => $folder,
            'filter' => $filter,
            'messages' => $messages,
            'has_more' => $hasMore,
            'read_ids' => $this->messages->readIds($user, $messages),
            'accounts' => $this->accounts->findForUser($user),
            'projects' => $this->projects->findVisibleFor($user),
        ];
    }

    /**
     * After toolbar actions: back to the list (from list hover toolbar) or to the message.
     */
    private function redirectBack(Request $request, Message $message): Response
    {
        return 'list' === $request->getPayload()->getString('return')
            ? $this->redirectToList($request)
            : $this->redirectToMessage($request, $message);
    }

    private function redirectToMessage(Request $request, Message $message, ?string $fragment = null): Response
    {
        $folder = $request->getPayload()->getString('folder', 'inbox');
        $url = $this->generateUrl('mail_show', [
            'folder' => \in_array($folder, self::FOLDERS, true) ? $folder : 'inbox',
            'id' => $message->getId(),
        ]);

        return $this->redirect($url.(null === $fragment ? '' : '#'.$fragment));
    }

    /**
     * Toast with an "Rückgängig" button that posts the inverse action for the changed messages.
     *
     * @param list<Message> $changed
     */
    private function undoFlash(string $text, string $action, array $changed, ?int $previousProject = null): void
    {
        $inverse = 'project' === $action ? 'project' : (MessageActions::INVERSE[$action] ?? null);
        if ([] === $changed) {
            $this->addFlash('info', 'mail.nothing_changed');

            return;
        }
        if (null === $inverse || \in_array($action, ['read', 'unread'], true)) {
            $this->addFlash('success', $text);

            return;
        }
        $this->addFlash('undo', [
            'text' => $text,
            'count' => \count($changed),
            'action' => $inverse,
            'ids' => array_map(static fn (Message $m): ?int => $m->getId(), $changed),
            'project' => $previousProject,
        ]);
    }

    /**
     * Presets of the "Wiedervorlage" menu or a datetime-local value.
     */
    private function snoozeUntil(string $value): ?\DateTimeImmutable
    {
        $today = new \DateTimeImmutable('today 08:00');

        return match ($value) {
            '' => null,
            'tomorrow' => $today->modify('+1 day'),
            'monday' => $today->modify('next monday'),
            'week' => $today->modify('+7 days'),
            default => \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value) ?: null,
        };
    }

    private function redirectAfterAction(Request $request, ?Message $message): Response
    {
        $redirect = $request->getPayload()->getString('redirect');
        if (str_starts_with($redirect, '/') && !str_starts_with($redirect, '//') && !str_contains($redirect, '\\')) {
            return $this->redirect($redirect);
        }
        if (null !== $message && 'list' !== $request->getPayload()->getString('return')) {
            return $this->redirectToMessage($request, $message);
        }

        return $this->redirectToList($request);
    }

    private function redirectToList(Request $request): Response
    {
        $folder = $request->getPayload()->getString('folder', 'inbox');
        $route = \in_array($folder, self::FOLDERS, true) && 'inbox' !== $folder ? 'mail_'.$folder : 'mail_inbox';
        /** @var array<string, string> $params */
        $params = $request->getPayload()->all('params');

        return $this->redirectToRoute($route, $params);
    }
}
