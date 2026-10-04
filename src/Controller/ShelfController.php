<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\ForumTopic;
use App\Entity\Message;
use App\Entity\ShelfItem;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Security\Voter\FolderVoter;
use App\Security\Voter\ForumVoter;
use App\Security\Voter\MessageVoter;
use App\Service\Shelf;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Personal shelf ("Merken"): files, email attachments, messages and forum topics.
 */
#[Route('/shelf')]
final class ShelfController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Shelf $shelf,
    ) {
    }

    #[Route('', name: 'shelf_index')]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('shelf/index.html.twig', ['items' => $this->shelf->items($user)]);
    }

    #[Route('/file/{id<\d+>}', name: 'shelf_toggle_file', methods: ['POST'])]
    #[IsGranted(FolderVoter::VIEW, 'file')]
    #[IsCsrfTokenValid('shelf')]
    public function toggleFile(Request $request, StoredFile $file, #[CurrentUser] User $user): Response
    {
        $this->toggle($user, $file);

        return $this->back($request, $this->generateUrl('file_show', ['id' => $file->getId()]));
    }

    #[Route('/message/{id<\d+>}/attachment/{attachment<\d+>}', name: 'shelf_toggle_attachment', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('shelf')]
    public function toggleAttachment(Request $request, Message $message, Attachment $attachment, #[CurrentUser] User $user): Response
    {
        if ($attachment->getMessage() !== $message) {
            throw $this->createNotFoundException();
        }
        $this->toggle($user, $attachment);

        return $this->back($request, $this->generateUrl('shelf_index'));
    }

    #[Route('/message/{id<\d+>}', name: 'shelf_toggle_message', methods: ['POST'])]
    #[IsGranted(MessageVoter::VIEW, 'message')]
    #[IsCsrfTokenValid('shelf')]
    public function toggleMessage(Request $request, Message $message, #[CurrentUser] User $user): Response
    {
        $this->toggle($user, $message);

        return $this->back($request, $this->generateUrl('shelf_index'));
    }

    #[Route('/topic/{id<\d+>}', name: 'shelf_toggle_topic', methods: ['POST'])]
    #[IsGranted(ForumVoter::VIEW, 'topic')]
    #[IsCsrfTokenValid('shelf')]
    public function toggleTopic(Request $request, ForumTopic $topic, #[CurrentUser] User $user): Response
    {
        $this->toggle($user, $topic);

        return $this->back($request, $this->generateUrl('forum_topic_show', ['id' => $topic->getId()]));
    }

    #[Route('/{id<\d+>}/remove', name: 'shelf_remove', methods: ['POST'])]
    #[IsCsrfTokenValid('shelf')]
    public function remove(ShelfItem $item, #[CurrentUser] User $user): Response
    {
        if ($item->getOwner() !== $user) {
            throw $this->createAccessDeniedException();
        }
        $this->em->remove($item);
        $this->em->flush();
        $this->addFlash('success', 'shelf.removed');

        return $this->redirectToRoute('shelf_index');
    }

    #[Route('/{id<\d+>}/download', name: 'shelf_download')]
    public function download(ShelfItem $item, #[CurrentUser] User $user): Response
    {
        $target = $item->getFileTarget();
        if ($item->getOwner() !== $user || null === $target || !$this->shelf->isAccessible($item)) {
            throw $this->createAccessDeniedException();
        }

        return $target instanceof StoredFile
            ? $this->redirectToRoute('file_download', ['id' => $target->getId()])
            : $this->redirectToRoute('mail_attachment', ['id' => $target->getMessage()->getId(), 'attachment' => $target->getId()]);
    }

    private function toggle(User $user, StoredFile|Attachment|Message|ForumTopic $target): void
    {
        $existing = $this->shelf->find($user, $target);
        if (null !== $existing) {
            $this->em->remove($existing);
            $this->addFlash('success', 'shelf.removed');
        } else {
            $this->em->persist(ShelfItem::for($user, $target));
            $this->addFlash('success', 'shelf.added');
        }
        $this->em->flush();
    }

    /**
     * Back to the page the button was on (only same-host referers).
     */
    private function back(Request $request, string $fallback): RedirectResponse
    {
        $referer = (string) $request->headers->get('referer');

        return $this->redirect(str_starts_with($referer, $request->getSchemeAndHttpHost().'/') ? $referer : $fallback);
    }
}
